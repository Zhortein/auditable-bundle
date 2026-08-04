<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'test_tx_application_audit_entry')]
final class ApplicationAuditEntry
{
    #[ORM\Column(length: 32)]
    private string $occurredAt;

    /**
     * @param array<string, mixed> $actorMetadata
     * @param array<string, mixed> $data
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::GUID)]
        private string $id,
        \DateTimeImmutable $occurredAt,
        #[ORM\Column(length: 64)]
        private string $action,
        #[ORM\Column(length: 32)]
        private string $level,
        #[ORM\Column(length: 255)]
        private string $title,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $description,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $context,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $subjectType,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $subjectIdentifier,
        #[ORM\Column(length: 128, nullable: true)]
        private ?string $actorType,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $actorIdentifier,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $impersonatorIdentifier,
        #[ORM\Column(type: Types::JSON)]
        private array $actorMetadata,
        #[ORM\Column]
        private bool $isAuto,
        #[ORM\Column(type: Types::JSON)]
        private array $data,
    ) {
        $this->occurredAt = $occurredAt->format('Y-m-d\\TH:i:s.uP');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->occurredAt);
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectIdentifier(): ?string
    {
        return $this->subjectIdentifier;
    }

    public function getActorType(): ?string
    {
        return $this->actorType;
    }

    public function getActorIdentifier(): ?string
    {
        return $this->actorIdentifier;
    }

    public function getImpersonatorIdentifier(): ?string
    {
        return $this->impersonatorIdentifier;
    }

    /** @return array<string, mixed> */
    public function getActorMetadata(): array
    {
        return $this->actorMetadata;
    }

    public function isAuto(): bool
    {
        return $this->isAuto;
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
    }
}
