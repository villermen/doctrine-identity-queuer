<?php

namespace Villermen\DoctrineIdentityQueuer\Test;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
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
        // Configure entity manager on in-memory database
        $this->entityManager = EntityManager::create(
            [
                'driver' => 'pdo_sqlite',
                'memory' => true
            ],
            Setup::createAnnotationMetadataConfiguration([__DIR__ . "/Entity"], true, null, null, false)
        );
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

        self::assertEquals(51, $user1->getId());
        self::assertEquals(50, $user2->getId());

        $this->entityManager->refresh($user1);

        self::assertEquals(51, $user1->getId());
    }
}
