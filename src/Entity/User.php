<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 */
class User
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $firstName;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $lastName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $deactivated = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $isCan;

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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

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

    public function getIsCan(): ?bool
    {
        return $this->isCan;
    }

    public function setIsCan(bool $isCan): self
    {
        $this->isCan = $isCan;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function createUserFromResponseVk(array $response): self
    {
        $this->setId($response['id']);
        $this->setLastName($response['last_name']);
        $this->setFirstName($response['first_name']);
        $this->setUpdatedAt(null);

        if (!empty($response['deactivated'])) {

            $this->setDeactivated($response['deactivated']);
        }

        if (empty($response['deactivated']) && isset($response['can_access_closed']) && false === $response['can_access_closed']) {
            $this->setIsCan(false);
        } else {
            $this->setIsCan(true);
        }

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

}
