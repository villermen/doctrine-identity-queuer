<?php

namespace Villermen\DoctrineIdentityQueuer\Test;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use PHPUnit\Framework\TestCase;
use Villermen\DoctrineIdentityQueuer\IdentityQueuer;
use Villermen\DoctrineIdentityQueuer\Test\Entity\User;

class IdentityQueuerTest extends TestCase
{
    /** @var EntityManager */
    protected $entityManager;

    /** @var IdentityQueuer */
    protected $identityQueuer;

    public function setUp(): void
    {
        $dbParams = [
            'user' => 'root',
            'password' => '',
            'host' => 'localhost',
            'dbname' => 'doctrine-identity-queuer',
            'driver' => 'pdo_mysql'
        ]; // TODO: SQLite?

        $config = Setup::createAnnotationMetadataConfiguration([__dir__ . "/Entity"], true);

        $this->entityManager = EntityManager::create($dbParams, $config);
        $this->identityQueuer = new IdentityQueuer($this->entityManager);
    }


    public function testQueueIdentity(): void
    {
        $this->identityQueuer->queueIdentity(User::class, 51);
        $this->identityQueuer->queueIdentity(User::class, 50);

        $user1 = new User('Fred');
        $user2 = new User('Paul');
        $user3 = new User('Jake');

        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);
        $this->entityManager->persist($user3);
        $this->entityManager->flush();

        self::assertEquals(51, $user1->getId());
        self::assertEquals(50, $user2->getId());

        $this->entityManager->refresh($user1);

        self::assertEquals(51, $user1->getId());
    }
}
