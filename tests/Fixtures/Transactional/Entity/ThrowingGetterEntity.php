<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class ThrowingGetterEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private string $identifier,
    ) {
        if ('' === $this->identifier) {
            throw new \InvalidArgumentException('Fixture identifier must not be empty.');
        }
    }

    public function getId(): never
    {
        throw new \LogicException('The getId() method must not be called.');
    }
}
