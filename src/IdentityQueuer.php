<?php

namespace Villermen\DoctrineIdentityQueuer;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;

class IdentityQueuer
{
    /** @var EntityManager */
    protected $entityManager;

    /** @var array [className => [type, AbstractIdGenerator]] */
    protected $originalGenerators = [];

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function addIdentity(string $className, $identity): void
    {
        $metadata = $this->entityManager->getClassMetadata($className);

        // Add the custom generator
        if (!($metadata->idGenerator instanceof QueuedIdentityGenerator)) {
            $this->originalGenerators[$className] = [
                $metadata->generatorType,
                $metadata->idGenerator
            ];

            $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_CUSTOM);
            $metadata->setIdGenerator(new QueuedIdentityGenerator($this));
        }

        // Queue the identity
        $metadata->idGenerator->queueIdentity($identity);
    }

    public function revert(string $className)
    {
        if (!isset($this->originalGenerators[$className])) {
            throw new \Exception('No original generator is registered for that class.');
        }

        $metadata = $this->entityManager->getClassMetadata($className);
        $metadata->setIdGeneratorType($this->originalGenerators[$className][0]);
        $metadata->setIdGenerator($this->originalGenerators[$className][1]);

        unset($this->originalGenerators[$className]);
    }
}