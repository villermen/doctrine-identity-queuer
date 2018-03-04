<?php

namespace Villermen\DoctrineIdentityQueuer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;

class IdentityQueuer
{
    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var mixed[][] [className => [identity1, identity2]] */
    protected $queuedIdentities;

    protected $eventRegistered = false;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function queueIdentity(string $className, $identity): void
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

    public function preFlush()
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
            $reflectionProperty = $metadata->getReflectionProperty($fieldName);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($entity, $identity);

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
        $this->entityManager->flush($queuedInserts);

        // Revert generators
        foreach($originalGenerators as $className => $generator) {
            $metadata = $this->entityManager->getClassMetadata($className);

            // TODO: Setting generator type back to AUTO is naive, but the original might have been resolved from AUTO and will not work for some reason
            // If IDENTITY was obtained (even though it was originally set to AUTO), it will try to add id as a parameter for insertion without binding it
            $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_AUTO);
            $metadata->setIdGenerator($generator);
        }

        // Add the previously detached inserts
        foreach($regularInserts as $regularInsert) {
            $this->entityManager->persist($regularInsert);
        }
    }
}