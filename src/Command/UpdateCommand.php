<?php

namespace App\Command;

use App\Entity\Comment;
use App\Repository\CommentRepository;
use App\Repository\GroupRepository;
use App\Repository\LikeRepository;
use App\Repository\UserRepository;
use App\Repository\WallRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use ErrorException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateCommand extends Command
{
    protected static $defaultName = 'app:update';
    protected static $defaultDescription = 'Updating information';
    protected LoggerInterface $logger;
    protected WallRepository $wallRepository;
    protected LikeRepository $likeRepository;
    protected CommentRepository $commentRepository;
    protected UserRepository $userRepository;
    protected GroupRepository $groupRepository;
    protected EntityManagerInterface $entityManager;

    use CommandTrait;
    use HelperTrait;

    public function __construct(LoggerInterface        $logger,
                                WallRepository         $wallRepository,
                                EntityManagerInterface $entityManager,
                                LikeRepository         $likeRepository,
                                CommentRepository      $commentRepository,
                                UserRepository         $userRepository,
                                GroupRepository        $groupRepository)
    {
        parent::__construct();

        $this->logger = $logger;
        $this->wallRepository = $wallRepository;
        $this->likeRepository = $likeRepository;
        $this->commentRepository = $commentRepository;
        $this->userRepository = $userRepository;
        $this->groupRepository = $groupRepository;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::REQUIRED, 'Screen name')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit posts')
            ->addOption('date_from', null, InputOption::VALUE_OPTIONAL, 'Date from (2022-07-04 05:00) ')
            ->addOption('date_to', null, InputOption::VALUE_OPTIONAL, 'Date to (2022-07-04 05:00)')
            ->addOption('likes', null, InputOption::VALUE_NONE, 'Loading Likes')
            ->addOption('comments', null, InputOption::VALUE_NONE, 'Loading Comments')
            ->addOption('no-interactive', null, InputOption::VALUE_NONE, 'Disable progressbar');
    }

    /**
     * @throws ErrorException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger();

        $io = new SymfonyStyle($input, $output);

        $screenNameArgument = $input->getArgument('arg1');
        $limitOption = $input->getOption('limit');
        $dateFromOption = $input->getOption('date_from');
        $dateToOption = $input->getOption('date_to');
        $likesOption = $input->getOption('likes');
        $commentsOption = $input->getOption('comments');
        $noInteractiveOption = $input->getOption('no-interactive');

        $progressBarCurrentPostDate = null;
        $progressBarLikesAll = null;
        $progressBarCommentsAll = null;
        $progressBarPostsAll = null;

        $counterLikesAll = null;
        $counterCommentsAll = null;
        $counterPostsAll = null;

        $infinity = true;

        if ($noInteractiveOption) {
            $counterLikesAll = 0;
            $counterCommentsAll = 0;
            $counterPostsAll = 0;
        } else {
            $section0 = $output->section();
            $section1 = $output->section();
            $section2 = $output->section();
            $section3 = $output->section();

            $formatBar = "<comment>%message:42s%</comment>";
            $progressBarCurrentPostDate = new ProgressBar($section0);
            $progressBarCurrentPostDate->setFormat($formatBar);
            $progressBarCurrentPostDate->setMessage('Req.API:[-] Post:[-]');

            $formatBar = "<comment>%message:35s% %current:6d%</comment>";
            $progressBarLikesAll = new ProgressBar($section1);
            $progressBarLikesAll->setFormat($formatBar);
            $progressBarLikesAll->setMessage('Total likes');

            $progressBarCommentsAll = new ProgressBar($section2);
            $progressBarCommentsAll->setFormat($formatBar);
            $progressBarCommentsAll->setMessage('Total comments');

            $formatBar = "<comment>%message:35s% %current:6d% </comment> <info>%elapsed:7s%</info> <error>%memory:6s%</error> ";
            $progressBarPostsAll = new ProgressBar($section3);
            $progressBarPostsAll->setFormat($formatBar);
            $progressBarPostsAll->setMessage('Total posts');
        }

        $objectId = $this->getObjectId($screenNameArgument);

        $dateFrom = $dateFromOption ? new DateTime($dateFromOption) : null;
        $dateTo = $dateToOption ? new DateTime($dateToOption) : null;


        $postsExists = $this->wallRepository->getIdsByOwner($objectId, $limitOption, $dateFrom, $dateTo);
        $postsExistsChunks = array_chunk($postsExists, 100);

        foreach ($postsExistsChunks as $postsExistsChunk) {

            $postsParam = array_map(function ($val) use ($objectId) {
                return $objectId . '_' . $val;
            }, $postsExistsChunk);

            $params = [
                'posts' => $postsParam,
                'extended' => 1
            ];

            $posts = $this->getPostsByIds($params);

            if ($this->isResponseEmpty($posts)) {
                continue;
            }

            // USERS
            if (!empty($posts['profiles'])) {
                $this->createOrUpdateUsers($posts['profiles']);
            }

            // GROUPS
            if (!empty($posts['groups'])) {
                $this->createOrUpdateGroups($posts['groups']);
            }

            foreach ($posts['items'] as $post) {

                if ($noInteractiveOption) {
                    $counterPostsAll++;
                } else {
                    $progressBarCurrentPostDate->advance();
                    $progressBarCurrentPostDate->setMessage(sprintf("Req.API:[%d] Post:[%s]",
                        self::$counterRequests,
                        DateTime::createFromFormat("U", $post['date'])->format('Y-m-d H:i:s')));

                    $progressBarPostsAll->advance();
                }

                $nowUTC = new DateTime('now', new DateTimeZone('UTC'));

                $wallEntity = $this->wallRepository->findOneBy([
                    'ownerId' => $post['owner_id'],
                    'id' => $post['id']])
                    ->setUpdatedAt($nowUTC);
                $this->entityManager->persist($wallEntity);
                $this->entityManager->flush();

                // DELETED
                if (isset($post['is_deleted']) && true === $post['is_deleted']) {

                    if ($wallEntity->getDeletedAt()) {

                        continue; // STOP
                    }

                    $this->logger->info('deleted', [$post]);

                    $wallEntity->setDeletedAt($nowUTC);
                    $this->entityManager->persist($wallEntity);
                    $this->entityManager->flush();

                    $this->likeRepository->deleteByItemId($wallEntity->getOwnerId(), $wallEntity->getId());
                    $this->commentRepository->deleteByItemId($wallEntity->getOwnerId(), $wallEntity->getId());

                    continue; // STOP
                }

                // LIKES
                if ($likesOption) {

                    $counterLikes = 0;
                    $offsetLikes = 0;
                    $countLikes = 1000;
                    $accumulatorLikes = [];

                    while ($infinity) {

                        $params = [
                            'type' => 'post',
                            'extended' => 1,
                            'owner_id' => $objectId,
                            'item_id' => $post['id'],
                            'count' => $countLikes,
                            'offset' => $offsetLikes
                        ];

                        $likes = $this->getLikes($params);
                        $likesIds = array_column($likes['items'], 'id');
                        $accumulatorLikes = array_unique(array_merge($accumulatorLikes, $likesIds));

                        if ($this->isResponseEmpty($likes)) {

                            if (0 < count($accumulatorLikes)) {
                                $likesExistsIds = $this->likeRepository->getExistsIds($wallEntity->getId());
                                $likesForDelete = array_diff($likesExistsIds, $accumulatorLikes);
                                $this->likeRepository->deleteByUserId($wallEntity->getId(), $likesForDelete);
                            }

                            break;
                        }

                        // USERS
                        if (!empty($likes['items'])) {
                            $this->createOrUpdateUsers($likes['items']);
                        }

                        if (0 === $counterLikes) {
                            $counterLikes = count($likes['items']);
                        }

                        if ($noInteractiveOption) {
                            $counterLikesAll += $counterLikes;
                        } else {
                            $progressBarLikesAll->advance($counterLikes);
                        }

                        $this->createOrUpdateLikes($likes['items'], 'post', $objectId, $wallEntity);

                        $offsetLikes += $countLikes;
                    }

                }

                // COMMENTS
                if ($commentsOption) {

                    $counterComments = 0;
                    $offsetComments = 0;
                    $countComments = 100;
                    $threadItemsCount = 10;
                    $accumulatorComments = [];

                    while ($infinity) {

                        $params = [
                            'post_id' => $post['id'],
                            'owner_id' => $objectId,
                            'sort' => 'desc',
                            'preview_length' => 0,
                            'extended' => 1,
                            'count' => $countComments,
                            'offset' => $offsetComments,
                            'thread_items_count' => $threadItemsCount
                        ];

                        $comments = $this->getComments($params);

                        $commentsIds = Comment::getCommentIds($comments, $threadItemsCount);

                        $accumulatorComments = array_unique(array_merge($accumulatorComments, $commentsIds));

                        if ($this->isResponseEmpty($comments)) {

                            $commentsExistsIds = $this->commentRepository->getExistsIds($wallEntity->getId());
                            $commentsForDelete = array_diff($commentsExistsIds, $accumulatorComments);
                            $this->commentRepository->deleteByUserId($wallEntity->getId(), $commentsForDelete);

                            break;
                        }

                        // USERS
                        if (!empty($comments['profiles'])) {
                            $this->createOrUpdateUsers($comments['profiles']);
                        }

                        // GROUPS
                        if (!empty($comments['groups'])) {
                            $this->createOrUpdateGroups($comments['groups']);
                        }

                        $threadsAndDeleted = $this->createOrUpdateComments($comments['items'], $objectId, $wallEntity, $threadItemsCount);

                        $counterComments = $threadsAndDeleted['count'];

                        if ($noInteractiveOption) {
                            $counterCommentsAll += $counterComments;
                        } else {
                            $progressBarCommentsAll->advance($counterComments);
                        }

                        $offsetComments += $countComments;


                        // THREADS
                        if (empty($threadsAndDeleted['threads'])) {
                            continue;
                        }

                        foreach ($threadsAndDeleted['threads'] as $thread) {

                            $offsetThreadComments = 0;

                            while ($infinity) {

                                $params = [
                                    'comment_id' => $thread,
                                    'owner_id' => $objectId,
                                    'sort' => 'desc',
                                    'preview_length' => 0,
                                    'extended' => 1,
                                    'count' => $countComments,
                                    'offset' => $offsetThreadComments
                                ];

                                $commentsThread = $this->getComments($params);
                                $commentsThreadIds = Comment::getCommentIds($commentsThread, null);
                                $accumulatorComments = array_unique(array_merge($accumulatorComments, $commentsThreadIds));

                                if ($this->isResponseEmpty($commentsThread)) {
                                    break;
                                }

                                // USERS
                                if (!empty($commentsThread['profiles'])) {
                                    $this->createOrUpdateUsers($commentsThread['profiles']);
                                }

                                // GROUPS
                                if (!empty($commentsThread['groups'])) {
                                    $this->createOrUpdateGroups($commentsThread['groups']);
                                }

                                $countThreadItems = $this->createOrUpdateComments($commentsThread['items'], $objectId, $wallEntity, $threadItemsCount);

                                $counterComments = $countThreadItems['count'];

                                if ($noInteractiveOption) {
                                    $counterCommentsAll += $counterComments;
                                } else {
                                    $progressBarCommentsAll->advance($counterComments);
                                }

                                $offsetThreadComments += $countComments;
                            }
                        }
                    }
                }
            }
        }
        if ($noInteractiveOption) {
            $this->table(
                $io,
                ['Likes', 'Comments', 'Posts'],
                [[$counterLikesAll, $counterCommentsAll, $counterPostsAll]]
            )
                ->render();
        } else {
            $progressBarCurrentPostDate->finish();
            $progressBarLikesAll->finish();
            $progressBarCommentsAll->finish();
            $progressBarPostsAll->finish();
        }
        return Command::SUCCESS;
    }
}
