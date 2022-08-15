<?php

namespace App\DataProcessing;

use App\Command\CommandTrait;
use App\Command\HelperTrait;
use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\Wall;
use App\Repository\CommentRepository;
use App\Repository\GroupRepository;
use App\Repository\LikeRepository;
use App\Repository\UserRepository;
use App\Repository\WallRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\ResultSetMapping;
use ErrorException;
use phpDocumentor\Reflection\DocBlockFactory;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class DataProcessing
{
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
                                EntityManagerInterface $entityManager,
                                WallRepository         $wallRepository,
                                LikeRepository         $likeRepository,
                                CommentRepository      $commentRepository,
                                UserRepository         $userRepository,
                                GroupRepository        $groupRepository)
    {

        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->wallRepository = $wallRepository;
        $this->likeRepository = $likeRepository;
        $this->commentRepository = $commentRepository;
        $this->userRepository = $userRepository;
        $this->groupRepository = $groupRepository;

    }

    /**
     * Список доступных методов
     * @return array
     * @throws ReflectionException
     */
    public function methods(): array
    {
        $arr = [];
        $class = new ReflectionClass(self::class);

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {

            switch ($method->name) {
                case '__construct':
                case 'methods':
                    continue 2;
            }

            $docMethod = $class->getMethod($method->name)
                ->getDocComment();

            $arr[mb_strtolower($method->name)] = DocBlockFactory::createInstance()
                ->create($docMethod)
                ->getSummary();

        }

        return $arr;
    }

    /**
     * Общее количество лайков, комментов, постов, и активных юзеров их сделавших
     *
     * @param int $objectId
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function statWall(int $objectId): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('posts', 'posts');
        $rsm->addScalarResult('likes', 'likes');
        $rsm->addScalarResult('comments', 'comments');
        $rsm->addScalarResult('users', 'users');

        $sql = <<<SQL

SELECT
    (SELECT COUNT(1) FROM `wall` WHERE owner_id = :owner_id) AS `posts`,
    (SELECT COUNT(1) FROM `like` WHERE owner_id = :owner_id) AS `likes`,
    (SELECT COUNT(1) FROM `comment` WHERE owner_id = :owner_id) AS `comments`,
    (SELECT COUNT(1) FROM
        (SELECT user.id FROM `user`
            JOIN wall ON wall.from_id = user.id AND wall.owner_id = :owner_id
        UNION
        SELECT user.id FROM `user`
            JOIN `like` ON `like`.user_id = user.id AND `like`.owner_id = :owner_id
        UNION
        SELECT user.id FROM `user`
            JOIN `comment` ON `comment`.from_id = user.id AND `comment`.owner_id = :owner_id
        ) AS sub
    ) AS `users`;
    
SQL;

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('owner_id', $objectId);

        return $query->getSingleResult();
    }

    /**
     * Посты юзеров в разных источниках
     * @param array|null $sourceIds
     * @param array $fromIds
     * @param int|null $limit
     * @param DateTime|null $dateFrom
     * @param DateTime|null $dateTo
     * @return array
     */
    public function usersPost(?array $sourceIds, array $fromIds, ?int $limit, ?DateTime $dateFrom, ?DateTime $dateTo): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Wall::class, 'wall');

        $rsm->addFieldResult('wall', 'id', 'id');
        $rsm->addFieldResult('wall', 'owner_id', 'ownerId');
        $rsm->addFieldResult('wall', 'from_id', 'fromId');
        $rsm->addFieldResult('wall', 'date', 'date');
        $rsm->addFieldResult('wall', 'text', 'text');
        $rsm->addFieldResult('wall', 'deleted_at', 'deletedAt');
        $rsm->addFieldResult('wall', 'updated_at', 'updatedAt');
        $rsm->addScalarResult('author_from_user_id', 'authorFromUserId');
        $rsm->addScalarResult('author_from_user_first_name', 'authorFromUserFirstName');
        $rsm->addScalarResult('author_from_user_last_name', 'authorFromUserLastName');
        $rsm->addScalarResult('author_from_group_id', 'authorFromGroupId');
        $rsm->addScalarResult('author_from_group_name', 'authorFromGroupName');


        $sqlWhere1 = '';
        $sqlWhere2 = '';
        $sqlWhere3 = '';
        $sqlWhere4 = '';
        $sqlWhere5 = '';
        $sqlLimit = '';

        if ($sourceIds) {
            $sqlWhere1 = 'AND w.owner_id IN(:sourceIds)';
        }

        if ($dateFrom) {
            $sqlWhere2 = 'AND w.date >= :dateFrom';
            $sqlWhere4 = 'AND w2.date >= :dateFrom';
        }

        if ($dateTo) {
            $sqlWhere3 = 'AND w.date <= :dateTo';
            $sqlWhere5 = 'AND w2.date <= :dateTo';
        }

        if ($limit) {
            $sqlLimit = 'LIMIT :limit';
        }

        $sql = <<<SQL

SELECT t2.*
FROM (
    SELECT
        DISTINCT w.owner_id,
        w.from_id
    FROM wall AS w
    WHERE w.from_id IN(:fromIds)
    $sqlWhere1
    $sqlWhere2
    $sqlWhere3
    ) AS t1,
LATERAL (
    SELECT w2.id,
           w2.owner_id,
           w2.from_id,
           w2.date,
           w2.text,
           w2.deleted_at,
           w2.updated_at,
           u.id AS `author_from_user_id`,
           u.first_name AS `author_from_user_first_name`,
           u.last_name AS `author_from_user_last_name`,
           g.id AS `author_from_group_id`,
           g.name AS `author_from_group_name`
    FROM wall AS w2
    LEFT JOIN user u
        ON u.id = w2.from_id
    LEFT JOIN `group` g 
        ON g.id = ABS(w2.from_id)
    WHERE w2.owner_id = t1.owner_id
      AND w2.from_id = t1.from_id
      $sqlWhere4
      $sqlWhere5
    ORDER BY w2.date DESC
    $sqlLimit
    ) AS t2;

SQL;

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('fromIds', $fromIds)
            ->setParameter('sourceIds', $sourceIds)
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->setParameter('limit', $limit ?: 10);

        return $query->getResult();
    }

    /**
     * Комментарии юзеров в разных источниках
     * @param array|null $sourceIds
     * @param array $fromIds
     * @param int|null $limit
     * @param DateTime|null $dateFrom
     * @param DateTime|null $dateTo
     * @return array
     */
    public function usersComment(?array $sourceIds, array $fromIds, ?int $limit, ?DateTime $dateFrom, ?DateTime $dateTo): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Comment::class, 'comment');

        $rsm->addFieldResult('comment', 'id', 'id');
        $rsm->addFieldResult('comment', 'from_id', 'fromId');
        $rsm->addFieldResult('comment', 'date', 'date');
        $rsm->addFieldResult('comment', 'text', 'text');
        $rsm->addFieldResult('comment', 'reply_user_id', 'replyUserId');
        $rsm->addFieldResult('comment', 'reply_comment_id', 'replyCommentId');
        $rsm->addFieldResult('comment', 'parent_id', 'parentId');
        $rsm->addFieldResult('comment', 'post_id', 'postId');
        $rsm->addFieldResult('comment', 'owner_id', 'ownerId');
        $rsm->addFieldResult('comment', 'deleted_at', 'deletedAt');
        $rsm->addFieldResult('comment', 'updated_at', 'updatedAt');

        $rsm->addScalarResult('author_from_user_id', 'authorFromUserId');
        $rsm->addScalarResult('author_from_user_first_name', 'authorFromUserFirstName');
        $rsm->addScalarResult('author_from_user_last_name', 'authorFromUserLastName');
        $rsm->addScalarResult('author_from_group_id', 'authorFromGroupId');
        $rsm->addScalarResult('author_from_group_name', 'authorFromGroupName');

        $rsm->addScalarResult('wall_from_user_id', 'wallFromUserId');
        $rsm->addScalarResult('wall_from_user_first_name', 'wallFromUserFirstName');
        $rsm->addScalarResult('wall_from_user_last_name', 'wallFromUserLastName');
        $rsm->addScalarResult('wall_from_group_id', 'wallFromGroupId');
        $rsm->addScalarResult('wall_from_group_name', 'wallFromGroupName');

        $rsm->addScalarResult('wall_id', 'wallId');

        $sqlWhere1 = '';
        $sqlWhere2 = '';
        $sqlWhere3 = '';
        $sqlWhere4 = '';
        $sqlWhere5 = '';
        $sqlLimit = '';

        if ($sourceIds) {
            $sqlWhere1 = 'AND c.owner_id IN(:sourceIds)';
        }

        if ($dateFrom) {
            $sqlWhere2 = 'AND c.date >= :dateFrom';
            $sqlWhere4 = 'AND c2.date >= :dateFrom';
        }

        if ($dateTo) {
            $sqlWhere3 = 'AND c.date <= :dateTo';
            $sqlWhere5 = 'AND c2.date <= :dateTo';
        }

        if ($limit) {
            $sqlLimit = 'LIMIT :limit';
        }

        $sql = <<<SQL

SELECT t2.*
FROM (
    SELECT
        DISTINCT c.owner_id,
        c.from_id
    FROM comment AS c
    WHERE c.from_id IN(:fromIds)
    $sqlWhere1
    $sqlWhere2
    $sqlWhere3
    ) AS t1,
LATERAL (
    SELECT c2.id,
           c2.from_id,
           c2.date,
           c2.text,
           c2.reply_user_id,
           c2.reply_comment_id,
           c2.parent_id,
           c2.post_id,
           c2.owner_id,
           c2.deleted_at,
           c2.updated_at,
           w.id AS wall_id,
           u.id AS wall_from_user_id,
           u.first_name AS wall_from_user_first_name,
           u.last_name AS wall_from_user_last_name,
           g.id AS wall_from_group_id,
           g.name AS wall_from_group_name,
           u2.id AS author_from_user_id,
           u2.first_name AS author_from_user_first_name,
           u2.last_name AS author_from_user_last_name,
           g2.id AS author_from_group_id,
           g2.name AS author_from_group_name
           
    FROM comment AS c2
    LEFT JOIN wall w
        ON c2.post_id = w.id
            AND c2.owner_id = w.owner_id
    LEFT JOIN user u
        ON u.id = w.from_id
    LEFT JOIN `group` g 
        ON g.id = ABS(w.from_id)
    LEFT JOIN user u2
        ON u2.id = c2.from_id
    LEFT JOIN `group` g2 
        ON g2.id = ABS(c2.from_id)
    WHERE c2.owner_id = t1.owner_id
      AND c2.from_id = t1.from_id
      $sqlWhere4
      $sqlWhere5
    ORDER BY c2.date DESC
    $sqlLimit
    ) AS t2;

SQL;

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('fromIds', $fromIds)
            ->setParameter('sourceIds', $sourceIds)
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->setParameter('limit', $limit ?: 10);

        return $query->getResult();
    }

    /**
     * Лайки юзеров в разных источниках
     * @param array|null $sourceIds
     * @param array $fromIds
     * @param int|null $limit
     * @return array
     */
    public function usersLike(?array $sourceIds, array $fromIds, ?int $limit): array
    {

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Like::class, 'like');

        $rsm->addFieldResult('like', 'type', 'type');
        $rsm->addFieldResult('like', 'owner_id', 'ownerId');
        $rsm->addFieldResult('like', 'item_id', 'itemId');
        $rsm->addFieldResult('like', 'user_id', 'userId');
        $rsm->addFieldResult('like', 'deleted_at', 'deletedAt');
        $rsm->addFieldResult('like', 'updated_at', 'updatedAt');

        $rsm->addScalarResult('author_from_user_id', 'authorFromUserId');
        $rsm->addScalarResult('author_from_user_first_name', 'authorFromUserFirstName');
        $rsm->addScalarResult('author_from_user_last_name', 'authorFromUserLastName');
        $rsm->addScalarResult('author_from_group_id', 'authorFromGroupId');
        $rsm->addScalarResult('author_from_group_name', 'authorFromGroupName');

        $rsm->addScalarResult('wall_from_user_id', 'wallFromUserId');
        $rsm->addScalarResult('wall_from_user_first_name', 'wallFromUserFirstName');
        $rsm->addScalarResult('wall_from_user_last_name', 'wallFromUserLastName');
        $rsm->addScalarResult('wall_from_group_id', 'wallFromGroupId');
        $rsm->addScalarResult('wall_from_group_name', 'wallFromGroupName');

        $rsm->addScalarResult('wall_id', 'wallId');
        $rsm->addScalarResult('wall_date', 'wallDate');
        $rsm->addScalarResult('wall_text', 'wallText');


        $sqlWhere1 = '';
        $sqlLimit = '';

        if ($sourceIds) {
            $sqlWhere1 = 'AND l.owner_id IN(:sourceIds)';
        }

        if ($limit) {
            $sqlLimit = 'LIMIT :limit';
        }

        $sql = <<<SQL

SELECT t2.*
FROM (
    SELECT 
        DISTINCT l.owner_id,
        l.user_id
    FROM `like` AS l
    WHERE l.user_id IN(:fromIds)
    $sqlWhere1
    ) AS t1,
LATERAL (
    SELECT l2.type,
           l2.owner_id,
           l2.item_id,
           l2.user_id,
           l2.deleted_at,
           l2.updated_at,
           w.id AS wall_id,
           w.text AS wall_text,
           w.date AS wall_date,
           u.id AS wall_from_user_id,
           u.first_name AS wall_from_user_first_name,
           u.last_name AS wall_from_user_last_name,
           g.id AS wall_from_group_id,
           g.name AS wall_from_group_name,
           u2.id AS author_from_user_id,
           u2.first_name AS author_from_user_first_name,
           u2.last_name AS author_from_user_last_name,
           g2.id AS author_from_group_id,
           g2.name AS author_from_group_name
    FROM `like` AS l2
    LEFT JOIN wall w 
        ON l2.item_id = w.id 
            AND l2.owner_id = w.owner_id
    LEFT JOIN user u 
        ON u.id = w.from_id
    LEFT JOIN `group` g 
        ON g.id = ABS(w.from_id)
    LEFT JOIN user u2 
        ON u2.id = l2.user_id
    LEFT JOIN `group` g2 
        ON g2.id = ABS(l2.user_id)
    WHERE l2.owner_id = t1.owner_id 
      AND l2.user_id = t1.user_id
    ORDER BY l2.item_id DESC
    $sqlLimit
    ) AS t2;
    
SQL;

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('fromIds', $fromIds)
            ->setParameter('sourceIds', $sourceIds)
            ->setParameter('limit', $limit ?: 10);

        return $query->getResult();
    }

    /**
     * Информация о страничке по имени из URL
     * @param array $screenNames
     * @return array
     * @throws ErrorException
     */
    public function whoIs(array $screenNames): array
    {

        if (empty($screenNames[0])) {
            throw new ErrorException('Empty screenName (target)');
        }

        $response = $this->resolveScreenName(["screen_name" => $screenNames[0]]);

        if (empty($response['object_id'])) {
            throw new ErrorException(sprintf('Id [%s] not Found', $screenNames[0]));
        }

        $out = [];

        if ('group' === $response['type'] || 'page' === $response['type']) {

            $out['id'] = -$response['object_id'];

            $params = ['group_ids' => $response['object_id']];
            $responseGroups = $this->getGroups($params);

            $out['name'] = $responseGroups[0]['name'];

            $this->createOrUpdateGroups($responseGroups);

        } elseif ('user' === $response['type']) {

            $out['id'] = $response['object_id'];

            $params = ['user_ids' => $response['object_id']];
            $responseUsers = $this->getUsers($params);

            $out['name'] = sprintf('%s %s', $responseUsers[0]['first_name'], $responseUsers[0]['last_name']);

            $this->createOrUpdateUsers($responseUsers);

        } else {
            throw new ErrorException('ScreenName not user, group, page');
        }

        $out['type'] = $response['type'];
        $out['screenName'] = $screenNames[0];

        return $out;
    }

}