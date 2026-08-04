<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class CompositeIdentifier
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private int $number,
        #[ORM\Id]
        #[ORM\Column]
        private string $country,
    ) {
        if ('' === $this->country || 0 === $this->number) {
            throw new \InvalidArgumentException('Fixture identifier parts must not be empty.');
        }
    }
}
