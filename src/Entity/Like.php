<?php

namespace App\Entity;

use App\DataProcessing\Author;
use App\Repository\LikeRepository;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass=LikeRepository::class)
 * @ORM\Table(name="`like`", indexes={
 *     @ORM\Index(columns={"owner_id","user_id"}),
 *     @ORM\Index(columns={"user_id","item_id"}),
 *     @ORM\Index(columns={"owner_id","item_id"})
 * })
 */
class Like
{

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=255)
     */
    private string $type;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private int $ownerId;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private int $itemId;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private int $userId;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $deletedAt = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $updatedAt = null;


    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function setOwnerId(int $ownerId): self
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): self
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @throws Exception
     */
    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt ?: new DateTime('now', new DateTimeZone('UTC'));

        return $this;
    }

    /**
     * @param Author $author
     * @return Author
     */
    public function getOwner(Author $author): Author
    {
        $author->createEntity($this->getOwnerId());
        return $author;
    }

    /**
     * @param Author $author
     * @return Author
     */
    public function getFrom(Author $author): Author
    {
        $author->createEntity($this->getUserId());
        return $author;
    }
}
