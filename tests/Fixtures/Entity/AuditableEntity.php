<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zhortein\AuditableBundle\Attribute\Auditable;
use Zhortein\AuditableBundle\Attribute\AuditField;
use Zhortein\AuditableBundle\Attribute\AuditIgnore;

#[ORM\Entity]
#[ORM\Table(name: 'test_auditable_entity')]
#[Auditable(label: 'Characterized entity', context: 'fixture-context')]
final class AuditableEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[AuditField(label: 'Public name')]
    private string $name;

    #[ORM\Column(length: 255)]
    #[AuditIgnore]
    private string $secret = 'secret';

    #[ORM\Column(length: 255)]
    private string $globallyIgnored = 'ignored';

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setGloballyIgnored(string $value): void
    {
        $this->globallyIgnored = $value;
    }

    public function getGloballyIgnored(): string
    {
        return $this->globallyIgnored;
    }
}
