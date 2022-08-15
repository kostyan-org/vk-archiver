<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     */
    public function add(User $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param User $entity
     * @param bool $flush
     */
    public function remove(User $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param User $user
     * @return void
     */
    public function createOrUpdateUser(User $user): void
    {
        $sql = 'INSERT INTO user (id, first_name, last_name, deactivated, is_can, updated_at)
            VALUES (:id, :first_name, :last_name, :deactivated, :is_can, :updated_at)
            ON DUPLICATE KEY UPDATE
                id = :id,
                first_name = :first_name,
                last_name = :last_name,
                deactivated = :deactivated,
                is_can = :is_can,
                updated_at = :updated_at;';

        $query = $this->_em->createNativeQuery($sql, new ResultSetMapping());
        $query->setParameter('id', $user->getId());
        $query->setParameter('first_name', $user->getFirstName());
        $query->setParameter('last_name', $user->getLastName());
        $query->setParameter('deactivated', $user->getDeactivated());
        $query->setParameter('is_can', $user->getIsCan());
        $query->setParameter('updated_at', $user->getUpdatedAt());

        $query->getResult();
    }

    /**
     * @param User[] $users
     * @return void
     */
    public function createOrUpdateUsers(array $users): void
    {

        $sql = 'INSERT INTO user (id, first_name, last_name, deactivated, is_can, updated_at)
            VALUES ';

        $i = 1;
        foreach ($users as $ignored) {
            $sql .= "(:id$i, :first_name$i, :last_name$i, :deactivated$i, :is_can$i, :updated_at$i),";
            $i++;
        }

        $sql = trim($sql, ",");

        $sql .= ' AS new ON DUPLICATE KEY UPDATE
                id = new.id,
                first_name = new.first_name,
                last_name = new.last_name,
                deactivated = new.deactivated,
                is_can = new.is_can,
                updated_at = new.updated_at;';

        $query = $this->_em->createNativeQuery($sql, new ResultSetMapping());

        $i = 1;
        foreach ($users as $user) {
            $query->setParameter("id$i", $user->getId());
            $query->setParameter("first_name$i", $user->getFirstName());
            $query->setParameter("last_name$i", $user->getLastName());
            $query->setParameter("deactivated$i", $user->getDeactivated());
            $query->setParameter("is_can$i", $user->getIsCan());
            $query->setParameter("updated_at$i", $user->getUpdatedAt());
            $i++;
        }

        $query->getResult();
    }
}
