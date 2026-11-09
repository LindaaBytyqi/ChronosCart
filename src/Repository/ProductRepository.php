<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Finds a product and locks its database row for writing.
     *
     * Must be called inside an active database transaction.
     */
    public function findForUpdate(int $id): ?Product
    {
    return $this->createQueryBuilder('p')
        ->where('p.id = :id')
        ->setParameter('id', $id)
        ->getQuery()
        ->setLockMode(LockMode::PESSIMISTIC_WRITE)
        ->getOneOrNullResult();
    }
    public function findActiveProducts(): array
    {
    return $this->createQueryBuilder('p')
        ->where('p.status = :status')
        ->setParameter('status', 'ACTIVE')
        ->orderBy('p.id', 'DESC')
        ->getQuery()
        ->getResult();
    }
}
