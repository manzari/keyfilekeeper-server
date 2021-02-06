<?php

namespace App\Entity;

use App\Repository\VolumeTokenRepository;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VolumeTokenRepository::class)
 */
class VolumeToken
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $token;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dateCreated;

    /**
     * @ORM\Column(type="date")
     */
    private $dateExpired;

    /**
     * @ORM\ManyToOne(targetEntity=Volume::class, inversedBy="volumeTokens")
     * @ORM\JoinColumn(nullable=false)
     */
    private $volume;

    public function __construct(Volume $volume, string $token)
    {
        $this->volume = $volume;
        $this->token = $token;
        $this->dateCreated = new DateTime();
        $this->dateExpired = new DateTime();
        $this->dateExpired->add(new DateInterval('P300D'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getDateCreated(): ?DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateExpired(): ?DateTimeInterface
    {
        return $this->dateExpired;
    }

    public function setDateExpired(DateTimeInterface $dateExpired): self
    {
        $this->dateExpired = $dateExpired;

        return $this;
    }

    public function getVolume(): ?Volume
    {
        return $this->volume;
    }

    public function setVolume(?Volume $volume): self
    {
        $this->volume = $volume;

        return $this;
    }

    public function isValid()
    {
        $now = new \DateTime();
        return ($now->getTimestamp() > $this->dateCreated->getTimestamp() && $now->getTimestamp() < $this->dateExpired->getTimestamp());
    }
}
