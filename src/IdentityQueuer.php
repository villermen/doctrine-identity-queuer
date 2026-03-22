<?php

namespace Villermen\DoctrineIdentityQueuer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;

class IdentityQueuer
{
    /** @var array<class-string, list<mixed>> */
    protected array $queuedIdentities = [];

    protected bool $eventRegistered = false;

    public function __construct(protected readonly EntityManagerInterface $entityManager)
    {
    }

    public function queueIdentity(string $className, mixed $identity): void
    {
        if (!isset($this->queuedIdentities[$className])) {
            $this->queuedIdentities[$className] = [];
        }

        $this->queuedIdentities[$className][] = $identity;

        if (!$this->eventRegistered) {
            $this->entityManager->getEventManager()->addEventListener(Events::preFlush, $this);

            $this->eventRegistered = true;
        }
    }

    public function preFlush(PreFlushEventArgs $event): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();

        // Create a list of queued inserts to check if magic needs to happen
        $queuedInserts = [];
        $regularInserts = [];
        /** @var AbstractIdGenerator[] $originalGenerators Mapped by class name */
        $originalGenerators = [];
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $className = get_class($entity);

            if (!isset($this->queuedIdentities[$className]) || count($this->queuedIdentities[$className]) === 0) {
                $regularInserts[] = $entity;
                continue;
            }

            $identity = array_shift($this->queuedIdentities[$className]);
            $metadata = $this->entityManager->getClassMetadata($className);

            // Override the identity
            $fieldName = $metadata->getSingleIdentifierFieldName();
            $propertyAccessor = $metadata->getPropertyAccessor($fieldName);
            $propertyAccessor->setValue($entity, $identity);

            // Override the generator to accept the new identity, saving it to restore later
            if (!($metadata->idGenerator instanceof AssignedGenerator)) {
                $originalGenerators[$className] = $metadata->idGenerator;

                $metadata->generatorType = ClassMetadata::GENERATOR_TYPE_CUSTOM;
                $metadata->idGenerator = new AssignedGenerator();
            }

            $queuedInserts[] = $entity;
        }

        if (count($queuedInserts) === 0) {
            return;
        }

        // Remove regular inserts from the context
        foreach($regularInserts as $regularInsert) {
            $this->entityManager->detach($regularInsert);
        }

        // Flush queued inserts only (or everything but the regular inserts depending on support)
        $this->entityManager->flush();

        // Revert generators
        foreach($originalGenerators as $className => $generator) {
            $metadata = $this->entityManager->getClassMetadata($className);
            $metadata->setIdGenerator($generator);

            // idGeneratorType is not changed back, as it might try to add the identity as a parameter without binding it
            // This is probably due to the entity originally using AUTO, but being resolved to IDENTITY or something similar.
        }

        // Add the previously detached inserts
        foreach($regularInserts as $regularInsert) {
            $this->entityManager->persist($regularInsert);
        }
    }
}