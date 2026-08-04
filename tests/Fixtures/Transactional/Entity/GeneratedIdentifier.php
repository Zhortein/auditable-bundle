<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class GeneratedIdentifier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    public function hasGeneratedIdentifier(): bool
    {
        return null !== $this->id;
    }
}
