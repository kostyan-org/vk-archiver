<?php

namespace App\Repository;

use App\Entity\Group;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Group|null find($id, $lockMode = null, $lockVersion = null)
 * @method Group|null findOneBy(array $criteria, array $orderBy = null)
 * @method Group[]    findAll()
 * @method Group[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    /**
     * @param Group $entity
     * @param bool $flush
     */
    public function add(Group $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param Group $entity
     * @param bool $flush
     */
    public function remove(Group $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param Group[] $groups
     * @return void
     */
    public function createOrUpdateGroups(array $groups): void
    {

        $sql = 'INSERT INTO `group` (id, name, type, is_closed, deactivated, updated_at)
            VALUES ';

        $i = 1;
        foreach ($groups as $ignored) {
            $sql .= "(:id$i, :name$i, :type$i, :is_closed$i, :deactivated$i, :updated_at$i),";
            $i++;
        }

        $sql = trim($sql, ",");

        $sql .= ' AS new ON DUPLICATE KEY UPDATE
                id = new.id,
                name = new.name,
                type = new.type,
                is_closed = new.is_closed,
                deactivated = new.deactivated,
                updated_at = new.updated_at;';

        $query = $this->_em->createNativeQuery($sql, new ResultSetMapping());

        $i = 1;
        foreach ($groups as $group) {
            $query->setParameter("id$i", $group->getId());
            $query->setParameter("name$i", $group->getName());
            $query->setParameter("type$i", $group->getType());
            $query->setParameter("is_closed$i", $group->getIsClosed());
            $query->setParameter("deactivated$i", $group->getDeactivated());
            $query->setParameter("updated_at$i", $group->getUpdatedAt());
            $i++;
        }

        $query->getResult();
    }
}
