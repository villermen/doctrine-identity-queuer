# doctrine-identity-queuer

#### Queue IDs for your next inserted entities.

[![CircleCI](https://circleci.com/gh/villermen/doctrine-identity-queuer.svg?style=svg)](https://circleci.com/gh/villermen/doctrine-identity-queuer)

## Description
This package adds an `IdentityQueuer` class, that allows you to set identities (IDs) of Doctrine entities in advance.
It is able to do this for entities that are configured to have a different identity generation strategy.

This package's main use-case is to explicitly set IDs of entities that are created somewhere in your services during unit tests.
It is not advised to use this package in production, as there are better ways of achieving custom ID generation outside of testing.

## Usage
```php
use Villermen\DoctrineIdentityQueuer\IdentityQueuer;

$identityQueuer = new IdentityQueuer($entityManager);
$identityQueuer->queueIdentity(User::class, 1234);

$user = new User()

$entityManager->persist($user);
$entityManager->flush();

// $user should have been given an id of 1234 instead of an automatically generated one!
```

## Installing
`composer require --dev villermen/doctrine-identity-queuer`

## How does it work?
`IdentityQueuer` will override the ID generator for entities when there are queued identities waiting for them.
It subscribes to Doctrine's `preFlush` event to flush the entities with queued identies first, before performing the actual flush.
Before performing the actual flush, the generators are changed back so that additional entities will behave like they originally would.
