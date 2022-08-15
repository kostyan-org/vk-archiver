<?php

namespace App\DataProcessing;

use App\Command\HelperTrait;
use App\Entity\Group;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 *
 */
class Author
{
    use HelperTrait;

    protected EntityManagerInterface $entityManager;
    /**
     * @var null|User|Group
     */
    private $entity = null;

    private string $name = '';

    private ?int $id = null;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param array $array
     * @param string $prefix
     * @return $this
     */
    public function setFromArray(array $array, string $prefix): self
    {

        if (isset($array[$prefix . 'UserId']) && null !== $array[$prefix . 'UserId']) {
            $firstName = $array[$prefix . 'UserFirstName'] ?? '';
            $lastName = $array[$prefix . 'UserLastName'] ?? '';
            $this->setName($firstName . ' ' . $lastName);
        }

        if (isset($array[$prefix . 'GroupId']) && null !== $array[$prefix . 'GroupId']) {

            $this->setName($array[$prefix . 'GroupName'] ?? null);
        }

        $this->setName(trim($this->getName(), ' '));
        return $this;
    }

    /**
     * @return null|User|Group
     */
    public function getEntity(): ?object
    {
        return $this->entity;
    }

    /**
     * @param object|null $entity
     * @return $this
     */
    public function setEntity(?object $entity): self
    {
        if (($entity instanceof User) || ($entity instanceof Group)) {
            $this->entity = $entity;
        }
        return $this;
    }

    /**
     * @param int|null $id
     * @return $this
     */
    public function createEntity(?int $id): self
    {
        if (null === $id) return $this;

        $this->entity = $this->isUser($id) ? new User() : new Group();
        $fieldName = $this->isUser($id) ? 'userId' : 'groupId';

        $rep = $this->entityManager->getRepository(get_class($this->entity));
        $this->entity = $rep->findOneBy([$fieldName => abs($id)]);

        return $this;
    }

    /**
     * @param string|null $name
     * @return $this
     */
    public function setName(?string $name): self
    {
        $this->name = $name ?? '';
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {

        if ($this->entity instanceof User) {

            $this->name = $this->entity->getFirstName() . ' ' . $this->entity->getLastName();

        } elseif ($this->entity instanceof Group) {

            $this->name = $this->entity->getName();
        }

        return $this->name;
    }

    /**
     * @param int|null $id
     * @return $this
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        if (null === $this->entity) return null;

        $this->id = $this->entity->getId();

        return $this->id;
    }
}
