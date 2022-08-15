<?php

namespace App\Entity;

use App\Repository\GroupRepository;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass=GroupRepository::class)
 * @ORM\Table(name="`group`")
 */
class Group
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $isClosed;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $deactivated = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $type;


    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getIsClosed(): ?bool
    {
        return $this->isClosed;
    }

    public function setIsClosed(bool $isClosed): self
    {
        $this->isClosed = $isClosed;

        return $this;
    }

    public function getDeactivated(): ?string
    {
        return $this->deactivated;
    }

    public function setDeactivated(?string $deactivated): self
    {
        $this->deactivated = $deactivated;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

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
     * @throws Exception
     */
    public function createGroupFromResponseVk(array $response): self
    {
        $this->setId($response['id']);
        $this->setName($response['name']);
        $this->setType($response['type']);
        $this->setIsClosed($response['is_closed']);
        $this->setUpdatedAt(null);

        if (!empty($response['deactivated'])) {

            $this->setDeactivated($response['deactivated']);
        }

        return $this;
    }

}
