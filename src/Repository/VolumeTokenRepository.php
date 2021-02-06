<?php

namespace App\Repository;

use App\Entity\VolumeToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method VolumeToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method VolumeToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method VolumeToken[]    findAll()
 * @method VolumeToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VolumeTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VolumeToken::class);
    }

    /**
     * @param VolumeToken $token
     * @return VolumeToken
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function save(VolumeToken $token)
    {
        $this->_em->persist($token);
        $this->_em->flush();
        return $token;
    }

    /**
     * @param VolumeToken $token
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delete(VolumeToken $token)
    {
        $this->_em->remove($token);
        $this->_em->flush();
    }
}
