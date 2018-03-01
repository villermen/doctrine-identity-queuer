<?php

namespace Villermen\DoctrineIdentityQueuer;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;

class QueuedIdentityGenerator extends AbstractIdGenerator
{
    /** @var IdentityQueuer */
    protected $identityQueuer;

    protected $queue = [];

    public function __construct(IdentityQueuer $identityQueuer)
    {
        $this->identityQueuer = $identityQueuer;
    }

    public function queueIdentity($identity): void
    {
        $this->queue[] = $identity;
    }

    public function generate(EntityManager $entityManager, $entity)
    {
        if (count($this->queue) === 0) {
            throw new \Exception('Queue is empty.');
        }

        $identity = array_shift($this->queue);

        // TODO: It will be reverted too early (I think isPostInsertGenerator() of the reverted generator will cause it to  up)
        if (count($this->queue) === 0) {
            $this->identityQueuer->revert(get_class($entity));
        }

        return $identity;
    }
}