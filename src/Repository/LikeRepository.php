<?php

namespace App\Repository;

use App\Entity\Like;
use DateTime;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @method Like|null find(array $id, $lockMode = null, $lockVersion = null)
 * @method Like|null findOneBy(array $criteria, array $orderBy = null)
 * @method Like[]    findAll()
 * @method Like[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Like::class);
    }

    /**
     * @param Like $entity
     * @param bool $flush
     */
    public function add(Like $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param Like $entity
     * @param bool $flush
     */
    public function remove(Like $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param int $ownerId
     * @param int $itemId
     * @param bool $flush
     * @return void
     * @throws Exception
     */
    public function deleteByItemId(int $ownerId, int $itemId, bool $flush = true): void
    {
        $nowUTC = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $qb = $this->createQueryBuilder('l');
        $qb = $qb->update()
            ->set('l.deletedAt', ':nowUTC')
            ->set('l.updatedAt', ':nowUTC')
            ->andWhere('l.itemId = :itemId')
            ->andWhere('l.ownerId = :ownerId')
            ->andWhere($qb->expr()->isNull('l.deletedAt'))
            ->setParameter('itemId', $itemId)
            ->setParameter('ownerId', $ownerId)
            ->setParameter('nowUTC', $nowUTC);

        if ($flush) {
            $qb->getQuery()
                ->execute();
        }
    }

    /**
     * @param int $wallId
     * @param array $userIds
     * @param bool $flush
     * @return void
     * @throws Exception
     */
    public function deleteByUserId(int $wallId, array $userIds, bool $flush = true): void
    {
        if (0 === count($userIds)) return;

        $nowUTC = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $qb = $this->createQueryBuilder('l');
        $qb = $qb->update()
            ->set('l.deletedAt', ':nowUTC')
            ->set('l.updatedAt', ':nowUTC')
            ->andWhere('l.itemId = :wallId')
            ->andWhere($qb->expr()->in('l.userId', $userIds))
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
        $qb = $this->createQueryBuilder('l');
        return $qb
            ->select('l.userId')
            ->andWhere($qb->expr()->isNull('l.deletedAt'))
            ->andWhere('l.itemId = :wallId')
            ->setParameter('wallId', $wallId)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @param int $value
     * @return array|null
     */
    public function distinctFromId(int $value): ?array
    {
        return $this->createQueryBuilder('l')
            ->select('DISTINCT l.userId')
            ->andWhere('l.ownerId = :val')
            ->setParameter('val', $value)
            ->orderBy('l.userId', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @param Like[] $likes
     * @return void
     */
    public function createOrUpdateLikes(array $likes): void
    {

        $sql = 'INSERT INTO `like` (type, owner_id, item_id, user_id, deleted_at, updated_at)
            VALUES ';

        $i = 1;
        foreach ($likes as $ignored) {
            $sql .= "(:type$i, :owner_id$i, :item_id$i, 
            :user_id$i, :deleted_at$i, :updated_at$i),";
            $i++;
        }

        $sql = trim($sql, ",");

        $sql .= ' AS new ON DUPLICATE KEY UPDATE
                type = new.type,
                owner_id = new.owner_id,
                item_id = new.item_id,
                user_id = new.user_id,
                deleted_at = new.deleted_at,
                updated_at = new.updated_at;';

        $query = $this->_em->createNativeQuery($sql, new ResultSetMapping());

        $i = 1;
        foreach ($likes as $like) {
            $query->setParameter("type$i", $like->getType());
            $query->setParameter("owner_id$i", $like->getOwnerId());
            $query->setParameter("item_id$i", $like->getItemId());
            $query->setParameter("user_id$i", $like->getUserId());
            $query->setParameter("deleted_at$i", $like->getDeletedAt());
            $query->setParameter("updated_at$i", $like->getUpdatedAt());

            $i++;
        }

        $query->getResult();
    }

}
