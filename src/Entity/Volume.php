<?php

namespace App\Entity;

use App\Repository\VolumeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=VolumeRepository::class)
 */
class Volume
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
    private $name;

    /**
     * @ORM\Column(type="string", length=512)
     */
    private $secret;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="volumes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\OneToMany(targetEntity=VolumeToken::class, mappedBy="volume", orphanRemoval=true)
     */
    private $volumeTokens;

    public function __construct(string $name, string $secret)
    {
        $this->name = $name;
        $this->secret = $secret;
        $this->volumeTokens = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
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

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    /**
     * @param UserInterface|null $user
     * @return $this
     */
    public function setUser(?UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection|VolumeToken[]
     */
    public function getVolumeTokens(): Collection
    {
        return $this->volumeTokens;
    }

    public function addVolumeToken(VolumeToken $volumeToken): self
    {
        if (!$this->volumeTokens->contains($volumeToken)) {
            $this->volumeTokens[] = $volumeToken;
            $volumeToken->setVolume($this);
        }

        return $this;
    }

    public function removeVolumeToken(VolumeToken $volumeToken): self
    {
        if ($this->volumeTokens->contains($volumeToken)) {
            $this->volumeTokens->removeElement($volumeToken);
            // set the owning side to null (unless already changed)
            if ($volumeToken->getVolume() === $this) {
                $volumeToken->setVolume(null);
            }
        }

        return $this;
    }
}
