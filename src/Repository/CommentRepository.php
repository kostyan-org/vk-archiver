<?php

namespace App\Repository;

use App\Entity\Comment;
use DateTime;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use function Doctrine\ORM\QueryBuilder;

/**
 * @method Comment|null find(array $id, $lockMode = null, $lockVersion = null)
 * @method Comment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Comment[]    findAll()
 * @method Comment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * @param Comment $entity
     * @param bool $flush
     */
    public function add(Comment $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param Comment $entity
     * @param bool $flush
     */
    public function remove(Comment $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param int $ownerId
     * @param int $postId
     * @param bool $flush
     * @return void
     * @throws Exception
     */
    public function deleteByItemId(int $ownerId, int $postId, bool $flush = true): void
    {
        $nowUTC = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $qb = $this->createQueryBuilder('c');
        $qb = $qb->update()
            ->set('c.deletedAt', ':nowUTC')
            ->set('c.updatedAt', ':nowUTC')
            ->andWhere('c.postId = :postId')
            ->andWhere('c.ownerId = :ownerId')
            ->andWhere($qb->expr()->isNull('c.deletedAt'))
            ->setParameter('postId', $postId)
            ->setParameter('ownerId', $ownerId)
            ->setParameter('nowUTC', $nowUTC);

        if ($flush) {
            $qb->getQuery()
                ->execute();
        }
    }

    /**
     * @param int $value
     * @return array|null
     */
    public function distinctFromId(int $value): ?array
    {
        return $this->createQueryBuilder('c')
            ->select('DISTINCT c.fromId')
            ->andWhere('c.ownerId = :val')
            ->setParameter('val', $value)
            ->orderBy('c.fromId', 'ASC')
//            ->setMaxResults(200)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @param Comment[] $comments
     * @return void
     */
    public function createOrUpdateComments(array $comments): void
    {

        $sql = 'INSERT INTO `comment` (id, from_id, date, text, reply_user_id, reply_comment_id, 
                       parent_id, post_id, owner_id, deleted_at, updated_at)
            VALUES ';

        $i = 1;
        foreach ($comments as $ignored) {
            $sql .= "(:id$i, :from_id$i, :date$i, :text$i, :reply_user_id$i, :reply_comment_id$i, 
            :parent_id$i, :post_id$i, :owner_id$i, :deleted_at$i, :updated_at$i),";
            $i++;
        }

        $sql = trim($sql, ",");

        $sql .= ' AS new ON DUPLICATE KEY UPDATE
                id = new.id,
                from_id = new.from_id,
                date = new.date,
                text = new.text,
                reply_user_id = new.reply_user_id,
                reply_comment_id = new.reply_comment_id,
                parent_id = new.parent_id,
                post_id = new.post_id,
                owner_id = new.owner_id,
                deleted_at = new.deleted_at,
                updated_at = new.updated_at;';

        $query = $this->_em->createNativeQuery($sql, new ResultSetMapping());

        $i = 1;
        foreach ($comments as $comment) {
            $query->setParameter("id$i", $comment->getId());
            $query->setParameter("from_id$i", $comment->getFromId());
            $query->setParameter("date$i", $comment->getDate());
            $query->setParameter("text$i", $comment->getText());
            $query->setParameter("reply_user_id$i", $comment->getReplyUserId());
            $query->setParameter("reply_comment_id$i", $comment->getReplyCommentId());
            $query->setParameter("parent_id$i", $comment->getParentId());
            $query->setParameter("post_id$i", $comment->getPostId());
            $query->setParameter("owner_id$i", $comment->getOwnerId());
            $query->setParameter("deleted_at$i", $comment->getDeletedAt());
            $query->setParameter("updated_at$i", $comment->getUpdatedAt());

            $i++;
        }

        $query->getResult();
    }

    /**
     * @param int $wallId
     * @param array $commentIds
     * @param bool $flush
     * @throws Exception
     */
    public function deleteByUserId(int $wallId, array $commentIds, bool $flush = true): void
    {
        if (0 === count($commentIds)) return;

        $nowUTC = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $qb = $this->createQueryBuilder('c');
        $qb = $qb->update()
            ->set('c.deletedAt', ':nowUTC')
            ->set('c.updatedAt', ':nowUTC')
            ->andWhere('c.postId = :wallId')
            ->andWhere($qb->expr()->in('c.id', $commentIds))
            ->setParameter('wallId', $wallId)
            ->setParameter('nowUTC', $nowUTC);

        if ($flush) {
            $qb->getQuery()
                ->execute();
        }
    }

    /**
     * @param int $wallId
     * @return array|null
     */
    public function getExistsIds(int $wallId): ?array
    {
        $qb = $this->createQueryBuilder('c');
        return $qb
            ->select('c.id')
            ->andWhere($qb->expr()->isNull('c.deletedAt'))
            ->andWhere('c.postId = :wallId')
            ->setParameter('wallId', $wallId)
            ->getQuery()
            ->getSingleColumnResult();
    }
}
