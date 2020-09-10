<?php

namespace App\Repository;

use App\Entity\Volume;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Volume|null find($id, $lockMode = null, $lockVersion = null)
 * @method Volume|null findOneBy(array $criteria, array $orderBy = null)
 * @method Volume[]    findAll()
 * @method Volume[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VolumeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Volume::class);
    }

    /**
     * @param Volume $volume
     * @return Volume|null
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function save(Volume $volume): ?Volume {
            $this->_em->persist($volume);
            $this->_em->flush();
            return $volume;
    }

    /**
     * @param Volume $volume
     * @throws ORMException
     */
    public function delete(Volume $volume)
    {
        $this->_em->remove($volume);
        $this->_em->flush();
    }
}
