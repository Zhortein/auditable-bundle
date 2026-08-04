<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class ReverseCompositeIdentifier
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private string $country,
        #[ORM\Id]
        #[ORM\Column]
        private int $number,
    ) {
        if ('' === $this->country || 0 === $this->number) {
            throw new \InvalidArgumentException('Fixture identifier parts must not be empty.');
        }
    }
}
