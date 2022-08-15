<?php

namespace App\Command;

use App\Entity\Comment;
use App\Entity\Group;
use App\Entity\Like;
use App\Entity\User;
use App\Entity\Wall;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use ErrorException;
use Exception;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

trait HelperTrait
{
    protected UserRepository $userRepository;
    protected GroupRepository $groupRepository;
    protected EntityManagerInterface $entityManager;

    /**
     * @return void
     */
    protected function entityReopen(): void
    {
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = $this->entityManager->create(
                $this->entityManager->getConnection(),
                $this->entityManager->getConfiguration()
            );
        }
    }

    /**
     * @param array $response
     * @return bool
     */
    protected function isResponseEmpty(array $response): bool
    {
        if (empty($response['items']) || (isset($response['count']) && 0 === $response['count'])) {
            $this->logger->info('isResponseEmpty', $response);
            return true;
        }
        return false;
    }

    /**
     * @param $array
     * @param $method
     * @param $value
     * @return object|null
     * @throws ReflectionException
     */
    protected function searchObjInArray($array, $method, $value): ?object
    {
        foreach ($array as $obj) {

            $reflectionMethod = new ReflectionMethod(get_class($obj), $method);
            if ($reflectionMethod->invoke($obj) === $value) {
                return $obj;
            }
        }
        return null;
    }

    /**
     * @param array<int> $ids
     * @param string $type user|group
     * @return array<int>
     */
    protected function filterIds(array $ids, string $type): array
    {

        $out = array_filter($ids, function ($v) use ($type) {

            if ('user' === $type) {
                return $v > 0;
            } elseif ('group' === $type) {
                return $v < 0;
            }
            return 0;
        });

        if ('group' === $type) {
            $out = array_map(
                function ($item) {
                    return abs($item);
                },
                $out
            );
        }

        return $out;
    }

    /**
     * @param array $users
     * @return void
     */
    protected function createOrUpdateUsers(array $users): void
    {
        if (empty($users)) return;

        $arr = [];
        foreach ($users as $profile) {

            $user = new User();
            $user->createUserFromResponseVk($profile);

            $arr[] = $user;
        }

        $this->userRepository->createOrUpdateUsers($arr); // Native SQL
    }

    /**
     * @param array $groups
     * @return void
     */
    protected function createOrUpdateGroups(array $groups): void
    {
        if (empty($groups)) return;

        $arr = [];
        foreach ($groups as $group) {

            $groupEntity = new Group();
            $groupEntity->createGroupFromResponseVk($group);

            $arr[] = $groupEntity;
        }

        $this->groupRepository->createOrUpdateGroups($arr); // Native SQL
    }

    /**
     * @param array $items
     * @param string $type
     * @param int $ownerId
     * @param Wall $entity
     * @return void
     */
    protected function createOrUpdateLikes(array $items, string $type, int $ownerId, Wall $entity): void
    {
        if (empty($items)) return;

        $arr = [];
        foreach ($items as $item) {

            $likeEntity = new Like();
            $likeEntity->setType($type)
                ->setUserId($item['id'])
                ->setOwnerId($ownerId)
                ->setItemId($entity->getId())
                ->setUpdatedAt(null);

            $arr[] = $likeEntity;
        }

        $this->likeRepository->createOrUpdateLikes($arr); // Native SQL
    }

    /**
     * @param array $items
     * @param int $ownerId
     * @param Wall $entity
     * @param int|null $threadItemsCount
     * @return array|null
     * @throws Exception
     */
    protected function createOrUpdateComments(array $items, int $ownerId, Wall $entity, ?int $threadItemsCount): ?array
    {
        if (empty($items)) return null;

        $arr = [];
        $threadsOut = [];
        $deletedOut = [];
        foreach ($items as $item) {

            $item['date'] = DateTime::createFromFormat("U", $item['date']);

            $commentEntity = new Comment();
            $commentEntity->setText($item['text'])
                ->setDate($item['date'])
                ->setFromId($item['from_id'])
                ->setParentId($item['parents_stack'][0] ?? null)
                ->setId($item['id'])
                ->setReplyCommentId($item['reply_to_comment'] ?? null)
                ->setReplyUserId($item['reply_to_user'] ?? null)
                ->setOwnerId($ownerId)
                ->setPostId($entity->getId())
                ->setUpdatedAt(null);

            if (isset($item['deleted']) && true === $item['deleted']) {

                $commentEntity->setDeletedAt(new DateTime('now', new DateTimeZone('UTC')));

                $this->logger->info('Comment deleted', [$item]);
                $deletedOut[] = $item['id'];

            }

            // thread_items_count
            if (isset($item['thread']['count']) && $item['thread']['count'] > 0 && $item['thread']['count'] <= $threadItemsCount) {

                $threads = $item['thread']['items'];

                foreach ($threads as $thread) {

                    $thread['date'] = DateTime::createFromFormat("U", $thread['date']);

                    $threadEntity = new Comment();
                    $threadEntity->setText($thread['text'])
                        ->setDate($thread['date'])
                        ->setFromId($thread['from_id'])
                        ->setParentId($thread['parents_stack'][0] ?? null)
                        ->setId($thread['id'])
                        ->setReplyCommentId($thread['reply_to_comment'] ?? null)
                        ->setReplyUserId($thread['reply_to_user'] ?? null)
                        ->setOwnerId($ownerId)
                        ->setPostId($entity->getId())
                        ->setUpdatedAt(null);

                    if (isset($thread['deleted']) && true === $thread['deleted']) {

                        $threadEntity->setDeletedAt(new DateTime('now', new DateTimeZone('UTC')));

                        $this->logger->info('Comment deleted', [$thread]);
                        $deletedOut[] = $thread['id'];

                    }

                    $arr[] = $threadEntity;
                }
            }

            if (isset($item['thread']['count']) && $item['thread']['count'] > $threadItemsCount) {
                $threadsOut[] = $item['id'];
            }

            $arr[] = $commentEntity;
        }

        $count = count($arr);
        if (count($arr) > 0) {
            $this->commentRepository->createOrUpdateComments($arr); // Native SQL
        }

        return [
            'threads' => $threadsOut,
            'deleted' => $deletedOut,
            'count' => $count
        ];
    }

    /**
     * @param array $screenNames
     * @return array
     * @throws ErrorException
     */
    protected function getObjectIds(array $screenNames): array
    {
        $arr = [];

        if (1 > count($screenNames)) {
            return $arr;
        }

        foreach ($screenNames as $screenName) {

            if (!$screenName) continue;

            $arr[] = $this->getObjectId($screenName);
        }

        return $arr;
    }

    /**
     * @param SymfonyStyle $io
     * @param array $headers
     * @param array $rows
     * @return Table
     */
    protected function table(SymfonyStyle $io, array $headers = [], array $rows = []): Table
    {
        return $io->createTable()
            ->setStyle('box') // 'default, borderless, compact, symfony-style-guide, box, box-double
            ->setHorizontal(false)
            ->setHeaders($headers)
            ->setRows($rows);

    }

    /**
     * @param int $id
     * @return bool
     */
    protected function isUser(int $id): bool
    {
        return $id > 0;
    }
}