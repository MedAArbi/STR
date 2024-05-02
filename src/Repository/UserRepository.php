<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\NoResultException;
/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }


    public function searchUsers($keyword)
    {
        return $this->createQueryBuilder('u')
            ->where('u.nom LIKE :keyword')
            ->orWhere('u.numTel LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->getQuery()
            ->getResult();
    }

    public function findByResetToken(string $token): ?User
    {
        try {
            return $this->createQueryBuilder('u')
                ->where('u.resetToken = :token')
                ->setParameter('token', $token)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        }
    }

    public function findOneByEmail(string $mail): ?User
    {
        try {
            return $this->createQueryBuilder('u')
                ->where('u.mail = :mail')
                ->setParameter('mail', $mail)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        }

    }public function countUsersByPhoneNumberPrefix()
    {
        $qb = $this->createQueryBuilder('u');
        $qb->select('SUBSTRING(u.numTel, 1, 1) AS phonePrefix, COUNT(u.id) AS userCount')
           ->groupBy('phonePrefix');

        $results = $qb->getQuery()->getResult();

        $counts = ['Orange' => 0, 'Ooredoo' => 0, 'Telecom' => 0];
        foreach ($results as $row) {
            switch ($row['phonePrefix']) {
                case '5':
                    $counts['Orange'] = (int)$row['userCount'];
                    break;
                case '2':
                    $counts['Ooredoo'] = (int)$row['userCount'];
                    break;
                case '9':
                    $counts['Telecom'] = (int)$row['userCount'];
                    break;
            }
        }

        return $counts;
    }

    
    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
