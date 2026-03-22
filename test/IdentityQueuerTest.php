<?php

namespace Villermen\DoctrineIdentityQueuer\Test;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Villermen\DoctrineIdentityQueuer\IdentityQueuer;
use Villermen\DoctrineIdentityQueuer\Test\Entity\User;

class IdentityQueuerTest extends TestCase
{
    private EntityManager $entityManager;

    protected IdentityQueuer $identityQueuer;

    public function setUp(): void
    {
        // Configure entity manager on in-memory database.
        $config = ORMSetup::createAttributeMetadataConfig(
            paths: [__DIR__ . '/Entity'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->entityManager = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

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

        self::assertEquals(51, $user1->id);
        self::assertEquals(50, $user2->id);

        $this->entityManager->refresh($user1);

        self::assertEquals(51, $user1->id);
    }
}
