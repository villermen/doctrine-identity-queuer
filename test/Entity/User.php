<?php

namespace Villermen\DoctrineIdentityQueuer\Test\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

#[Entity]
class User
{
    #[Id]
    #[Column(type: 'integer')]
    #[GeneratedValue]
    public int $id;

    #[Column(type: 'string', unique: true)]
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}