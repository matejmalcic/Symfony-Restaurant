<?php

namespace App\Repository;

use App\Entity\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Status|null find($id, $lockMode = null, $lockVersion = null)
 * @method Status|null findOneBy(array $criteria, array $orderBy = null)
 * @method Status[]    findAll()
 * @method Status[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Status::class);
    }

    public function findStatus($value, $direction)
    {
        if($direction === 'prev'){
            $orderBy= 'DESC';
            $sql = 's.number <' . $value;
        }elseif($direction === 'next'){
            $orderBy= 'ASC';
            $sql = 's.number >' . $value;
        }else{
            return false;
        }

        return $this->createQueryBuilder('s')
            ->orderBy('s.number', $orderBy)
            ->where($sql)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
    }
}
