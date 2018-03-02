<?php

namespace Villermen\DoctrineIdentityQueuer;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;

class QueuedIdentityGenerator extends AbstractIdGenerator
{
    protected $queue = [];

    protected $lastIdentity;

    public function queueIdentity($identity): void
    {
        $this->queue[] = $identity;
    }

    public function generate(EntityManager $entityManager, $entity)
    {
        // Generator can't be switched mid-flush, so let's not switch at all and approach the identity generator as a fallback
        if (count($this->queue) === 0) {
            return ++$this->lastIdentity;
        }

        $identity = array_shift($this->queue);

        $this->lastIdentity = $identity;

        return $identity;
    }
}