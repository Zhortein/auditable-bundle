<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Zhortein\AuditableBundle\Repository\AuditEntryRepository;

#[ORM\Entity(repositoryClass: AuditEntryRepository::class)]
#[ORM\Table(name: 'audit_entry', options: ['comment' => 'Audit trail entries'])]
#[ORM\Index(columns: ['occurred_at'], name: 'idx_audit_entry_occurred_at')]
#[ORM\Index(columns: ['entity_class', 'entity_id'], name: 'idx_audit_entry_entity')]
final class AuditEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $action = 'log';

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $level = 'info';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $context = null;

    #[ORM\Column(name: 'entity_class', type: Types::STRING, length: 255, nullable: true)]
    private ?string $entityClass = null;

    #[ORM\Column(name: 'entity_id', type: Types::STRING, length: 64, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(name: 'actor_id', type: Types::STRING, length: 64, nullable: true)]
    private ?string $actorId = null;

    #[ORM\Column(name: 'impersonator_id', type: Types::STRING, length: 64, nullable: true)]
    private ?string $impersonatorId = null;

    #[ORM\Column(name: 'is_auto', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isAuto = false;

    /** @var array<int|string, mixed> */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function setEntityClass(?string $entityClass): self
    {
        $this->entityClass = $entityClass;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function setActorId(?string $actorId): self
    {
        $this->actorId = $actorId;

        return $this;
    }

    public function getImpersonatorId(): ?string
    {
        return $this->impersonatorId;
    }

    public function setImpersonatorId(?string $impersonatorId): self
    {
        $this->impersonatorId = $impersonatorId;

        return $this;
    }

    public function isAuto(): bool
    {
        return $this->isAuto;
    }

    public function setIsAuto(bool $isAuto): self
    {
        $this->isAuto = $isAuto;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getData(): array
    {
        return $this->data ?? [];
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
