<?php


namespace App\Entity;


use Symfony\Component\Security\Core\User\UserInterface;

class TokenUser implements UserInterface
{
    /** @var int $volumeTokenId */
    private $volumeTokenId;

    public function __construct(int $volumeTokenId)
    {
        $this->volumeTokenId = $volumeTokenId;
    }

    public function getRoles()
    {
        return ['ROLE_DEVICE'];
    }

    /**
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, ['ROLE_DEVICE']);
    }

    public function getVolumeTokenId()
    {
        return $this->volumeTokenId;
    }

    public function getPassword()
    {
    }

    public function getSalt()
    {
    }

    public function getUsername()
    {
    }

    public function eraseCredentials()
    {
    }
}