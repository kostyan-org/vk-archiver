<?php

namespace App\Command;

use App\Entity\Wall;
use App\Repository\CommentRepository;
use App\Repository\GroupRepository;
use App\Repository\LikeRepository;
use App\Repository\UserRepository;
use App\Repository\WallRepository;
use DateTime;
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

class DownloadCommand extends Command
{
    protected static $defaultName = 'app:download';
    protected static $defaultDescription = 'Loading information';
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
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'Offset of posts')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit')
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
        $likesOption = $input->getOption('likes');
        $commentsOption = $input->getOption('comments');
        $offsetOption = $input->getOption('offset');
        $limitOption = $input->getOption('limit');
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
        $offsetPosts = $offsetOption ?: 0;
        $countPosts = 100;

        while ($infinity) {

            $params = [
                'owner_id' => $objectId,
                'offset' => $offsetPosts,
                'count' => $countPosts,
                'filter' => 'all',
                'extended' => 1
            ];

            $posts = $this->getPosts($params);

            if ($this->isResponseEmpty($posts)) {
                break;
            }

            $postsIds = array_column($posts['items'], 'id');
            $existsPostsIds = $this->wallRepository->getExistsIds($postsIds, $objectId);
            $newPostsIds = array_diff($postsIds, $existsPostsIds);

            if (empty($newPostsIds)) {
                break;
            }

            // USERS
            if (!empty($posts['profiles'])) {
                $this->createOrUpdateUsers($posts['profiles']);
            }

            // GROUPS
            if (!empty($posts['groups'])) {
                $this->createOrUpdateGroups($posts['groups']);
            }

            foreach ($newPostsIds as $k => $v) {

                $progress = $noInteractiveOption ? $counterPostsAll : $progressBarPostsAll->getProgress();

                if ($limitOption && $limitOption <= $progress) {

                    $this->logger->info('Stop limit', [
                        'limitOption' => $limitOption,
                        'progress' => $progress]);

                    break 2; // stop
                }

                $post = $posts['items'][$k];

                $post['date'] = DateTime::createFromFormat("U", $post['date']);

                if ($noInteractiveOption) {
                    $counterPostsAll++;
                } else {
                    $progressBarCurrentPostDate->advance();
                    $progressBarCurrentPostDate->setMessage(sprintf("Req.API:[%d] Post:[%s]",
                        self::$counterRequests,
                        $post['date']->format('Y-m-d H:i:s')));
                    $progressBarPostsAll->advance();
                }

                if ($post['from_id'] === $post['owner_id'] && !empty($post['signer_id'])) {
                    $post['from_id'] = $post['signer_id'];
                }

                $wallEntity = new Wall();
                $wallEntity->setId($post['id'])
                    ->setOwnerId($post['owner_id'])
                    ->setFromId($post['from_id'])
                    ->setDate($post['date'])
                    ->setText($post['text'])
                    ->setUpdatedAt(null);

                $this->entityManager->persist($wallEntity);
                $this->entityManager->flush();

                // LIKES
                if ($likesOption && $post['likes']['count'] > 0) {

                    $offsetLikes = 0;
                    $countLikes = 1000;

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

                        if ($this->isResponseEmpty($likes)) {
                            break;
                        }

                        // ONLY USERS, NO GROUPS
                        if (!empty($likes['items'])) {
                            $this->createOrUpdateUsers($likes['items']);
                        }

                        $counterNewLikes = count($likes['items']);

                        if ($noInteractiveOption) {
                            $counterLikesAll += $counterNewLikes;
                        } else {
                            $progressBarLikesAll->advance($counterNewLikes);
                        }


                        $this->createOrUpdateLikes($likes['items'], 'post', $objectId, $wallEntity);

                        $offsetLikes += $countLikes;


                    }
                }

                // COMMENTS
                if ($commentsOption && $post['comments']['count'] > 0) {

                    $offsetComments = 0;
                    $countComments = 100;
                    $threadItemsCount = 10;

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

                        if ($this->isResponseEmpty($comments)) {
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

                        $counterNewComments = $threadsAndDeleted['count'];

                        if ($noInteractiveOption) {
                            $counterCommentsAll += $counterNewComments;
                        } else {
                            $progressBarCommentsAll->advance($counterNewComments);
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

                                $counterNewComments = $countThreadItems['count'];

                                if ($noInteractiveOption) {
                                    $counterCommentsAll += $counterNewComments;
                                } else {
                                    $progressBarCommentsAll->advance($counterNewComments);
                                }

                                $offsetThreadComments += $countComments;
                            }
                        }
                    }
                }
            }

            $offsetPosts += $countPosts;
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
