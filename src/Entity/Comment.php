<?php

namespace App\Entity;

use App\DataProcessing\Author;
use App\Repository\CommentRepository;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass=CommentRepository::class)
 * @ORM\Table(indexes={
 *     @ORM\Index(columns={"owner_id","from_id"}),
 *     @ORM\Index(columns={"owner_id","post_id"}),
 * })
 */
class Comment
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\Column(type="integer")
     */
    private int $fromId;

    /**
     * @ORM\Column(type="datetime")
     */
    private DateTimeInterface $date;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $text = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $replyUserId = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $replyCommentId = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $parentId = null;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private int $postId;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private int $ownerId;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $deletedAt = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getFromId(): ?int
    {
        return $this->fromId;
    }

    public function setFromId(int $fromId): self
    {
        $this->fromId = $fromId;

        return $this;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getReplyUserId(): ?int
    {
        return $this->replyUserId;
    }

    public function setReplyUserId(?int $replyUserId): self
    {
        $this->replyUserId = $replyUserId;

        return $this;
    }

    public function getReplyCommentId(): ?int
    {
        return $this->replyCommentId;
    }

    public function setReplyCommentId(?int $replyCommentId): self
    {
        $this->replyCommentId = $replyCommentId;

        return $this;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): self
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getPostId(): ?int
    {
        return $this->postId;
    }

    public function setPostId(int $postId): self
    {
        $this->postId = $postId;

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
        $author->createEntity($this->getFromId());
        return $author;
    }

    public static function getCommentIds(array $response, ?int $threadItemsCount): array
    {
        $ids = [];

        if (0 === count($response['items'])) return $ids;

        $ids = array_column($response['items'], 'id');

        if (!$threadItemsCount) return $ids;

        foreach ($response['items'] as $item) {

            if (isset($item['thread']) && $item['thread']['count'] <= $threadItemsCount) {

                $ids = array_merge($ids, array_column($item['thread']['items'], 'id'));
            }
        }

        return $ids;
    }
}
