<?php

namespace App\Command;

use App\DataProcessing\Author;
use App\DataProcessing\DataProcessing;
use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\Wall;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use ErrorException;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatCommand extends Command
{
    protected static $defaultName = 'app:stat';
    protected static $defaultDescription = 'Statistics and information';
    protected LoggerInterface $logger;
    protected EntityManagerInterface $entityManager;
    protected DataProcessing $dp;
    protected Author $author;

    use CommandTrait;
    use HelperTrait;

    public function __construct(LoggerInterface        $logger,
                                EntityManagerInterface $entityManager,
                                DataProcessing         $dataProcessing,
                                Author                 $author)
    {
        parent::__construct();

        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->dp = $dataProcessing;
        $this->author = $author;
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit posts')
            ->addOption('date_from', null, InputOption::VALUE_OPTIONAL, 'Date from (2022-07-04 05:00) ')
            ->addOption('date_to', null, InputOption::VALUE_OPTIONAL, 'Date to (2022-07-04 05:00)')
            ->addOption('method', null, InputOption::VALUE_OPTIONAL, 'Method')
            ->addOption('target', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Target screen name')
            ->addOption('source', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Source screen name')
            ->addOption('methods', null, InputOption::VALUE_NONE, 'Get list');
    }

    /**
     * @throws ReflectionException
     * @throws NonUniqueResultException
     * @throws ErrorException
     * @throws NoResultException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger();

        $io = new SymfonyStyle($input, $output);

        $limitOption = $input->getOption('limit');
        $dateFromOption = $input->getOption('date_from');
        $dateToOption = $input->getOption('date_to');
        $methodsOption = $input->getOption('methods');
        $methodOption = $input->getOption('method');
        $targetOption = $input->getOption('target');
        $sourceOption = $input->getOption('source');

        $sourceIds = $this->getObjectIds($sourceOption);
        $targetIds = $this->getObjectIds($targetOption);

        $dateFrom = $dateFromOption ? new DateTime($dateFromOption) : null;
        $dateTo = $dateToOption ? new DateTime($dateToOption) : null;

        if ($methodsOption) {

            $result = $this->dp->methods();
            $io->newLine();
            $this->table(
                $io,
                array_keys($result),
                [array_values($result)]
            )
                ->setHorizontal(true)
                ->render();
            $io->newLine();

            return Command::SUCCESS;
        }

        if (!$methodOption) {
            throw new InvalidOptionException('Empty method');
        }

        $this->table(
            $io,
            ['Sources', 'Targets'],
            [[
                implode("\n", $sourceIds),
                implode("\n", $targetIds)
            ]]
        )
            ->render();

        if ($methodOption === 'statwall') {

            if (1 > count($sourceIds)) {
                throw new InvalidOptionException('Empty source');
            }

            $result = $this->dp->statWall($sourceIds[0]);

            $io->newLine();
            $this->table(
                $io,
                array_keys($result),
                [array_values($result)]
            )
                ->render();
            $io->newLine();
            return Command::SUCCESS;

        } elseif ($methodOption === 'userspost') {

            if (1 > count($targetIds)) {
                throw new InvalidOptionException('Empty target');
            }

            $result = $this->dp->usersPost($sourceIds, $targetIds, $limitOption, $dateFrom, $dateTo);

            $rows = [];
            foreach ($result as $key => $item) {

                /** @var Wall $item */
                $rows[$key]['link'] = sprintf('https://vk.com/wall%s_%s',
                    $item[0]->getOwnerId(),
                    $item[0]->getId());
                $rows[$key]['target'] = $this->author
                    ->setFromArray((array)$item, 'authorFrom')
                    ->getName();
                $rows[$key]['date'] = $item[0]->getDate()->format('Y/m/d');

                $text = mb_strcut($item[0]->getText(), 0, 100);
                $rows[$key]['text'] = $item[0]->getDeletedAt() ? '<error>' . $text . '</error>' : $text;
            }


            $io->newLine();
            $this->table(
                $io,
                ['link', 'target', 'date', 'text'],
                array_values($rows)
            )
                ->render();
            $io->newLine();

            return Command::SUCCESS;


        } elseif ($methodOption === 'userscomment') {

            if (1 > count($targetIds)) {
                throw new InvalidOptionException('Empty target');
            }

            $result = $this->dp->usersComment($sourceIds, $targetIds, $limitOption, $dateFrom, $dateTo);

            $rows = [];
            foreach ($result as $key => $item) {
                /** @var Comment $item */
                $rows[$key]['link'] = sprintf('https://vk.com/wall%s_%s',
                    $item[0]->getOwnerId(),
                    $item[0]->getPostId());
                $rows[$key]['targetUser'] = $this->author
                    ->setFromArray((array)$item, 'authorFrom')
                    ->getName();
                $rows[$key]['post'] = $this->author
                    ->setFromArray((array)$item, 'wallFrom')
                    ->getName();
                $rows[$key]['date'] = $item[0]->getDate()->format('Y/m/d');

                $text = mb_strcut($item[0]->getText(), 0, 100);
                $rows[$key]['text'] = $item[0]->getDeletedAt() ? '<error>' . $text . '</error>' : $text;
            }


            $io->newLine();
            $this->table(
                $io,
                ['link', 'target', 'post', 'date', 'text'],
                array_values($rows)
            )
                ->render();
            $io->newLine();

            return Command::SUCCESS;


        } elseif ($methodOption === 'userslike') {

            if (1 > count($targetIds)) {
                throw new InvalidOptionException('Empty target');
            }

            $result = $this->dp->usersLike($sourceIds, $targetIds, $limitOption);

            $rows = [];
            foreach ($result as $key => $item) {

                /** @var Like $item */
                $rows[$key]['link'] = sprintf('https://vk.com/wall%s_%s',
                    $item[0]->getOwnerId(),
                    $item[0]->getItemId());
                $rows[$key]['targetUser'] = $this->author
                    ->setFromArray((array)$item, 'authorFrom')
                    ->getName();
                $rows[$key]['post'] = $this->author
                    ->setFromArray((array)$item, 'wallFrom')
                    ->getName();
                $rows[$key]['date'] = (new DateTime($item['wallDate']))->format('Y/m/d');

                $text = mb_strcut($item['wallText'], 0, 100);
                $rows[$key]['text'] = $item[0]->getDeletedAt() ? '<error>' . $text . '</error>' : $text;
            }

            $io->newLine();
            $this->table(
                $io,
                ['link', 'target', 'post', 'date', 'text'],
                array_values($rows)
            )
                ->render();
            $io->newLine();

            return Command::SUCCESS;

        } elseif ($methodOption === 'whois') {

            if (1 > count($targetOption)) {
                throw new InvalidOptionException('Empty target');
            }

            $result = $this->dp->whois($targetOption);

            $io->newLine();
            $this->table(
                $io,
                array_keys($result),
                [array_values($result)]
            )
                ->setHorizontal(true)
                ->render();
            $io->newLine();

            return Command::SUCCESS;

        } else {
            throw new InvalidOptionException(sprintf('Method [%s] not found', $methodOption));
        }


    }
}
