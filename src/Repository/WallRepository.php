<?php

namespace App\Repository;

use App\Entity\Wall;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Wall|null find(array $id, $lockMode = null, $lockVersion = null)
 * @method Wall|null findOneBy(array $criteria, array $orderBy = null)
 * @method Wall[]    findAll()
 * @method Wall[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wall::class);
    }

    /**
     * @param Wall $entity
     * @param bool $flush
     */
    public function add(Wall $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param Wall $entity
     * @param bool $flush
     */
    public function remove(Wall $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param int $value
     * @return array|null
     */
    public function distinctFromId(int $value): ?array
    {
        return $this->createQueryBuilder('w')
            ->select('DISTINCT w.fromId')
            ->andWhere('w.ownerId = :val')
            ->setParameter('val', $value)
            ->orderBy('w.fromId', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @param int $ownerId
     * @param int|null $limit
     * @param DateTime|null $dateFrom
     * @param DateTime|null $dateTo
     * @return array|null
     */
    public function getIdsByOwner(int $ownerId, int $limit = null, DateTime $dateFrom = null, DateTime $dateTo = null): ?array
    {
        $qb = $this->createQueryBuilder('w')
            ->select('w.id')
            ->andWhere('w.ownerId = :ownerId')
            ->setParameter('ownerId', $ownerId);

        if ($dateFrom) {
            $qb->andWhere('w.date >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo) {
            $qb->andWhere('w.date <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        $qb->orderBy('w.date', 'DESC');

        return $qb->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @param array $ids
     * @param int $ownerId
     * @return array|null
     */
    public function getExistsIds(array $ids, int $ownerId): ?array
    {
        $qb = $this->createQueryBuilder('w');
        return $qb
            ->select('w.id')
            ->andWhere($qb->expr()->in('w.id', $ids))
            ->andWhere($qb->expr()->isNull('w.deletedAt'))
            ->andWhere('w.ownerId = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->orderBy('w.date', 'DESC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
