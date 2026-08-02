<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Model;

final readonly class AuditActor
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $type,
        public ?string $identifier = null,
        public ?string $impersonatorIdentifier = null,
        public array $metadata = [],
    ) {
        if ('' === trim($type)) {
            throw new \InvalidArgumentException('The audit actor type must not be empty.');
        }
        if (null !== $identifier && '' === trim($identifier)) {
            throw new \InvalidArgumentException('The audit actor identifier must not be empty when provided.');
        }
        if (null !== $impersonatorIdentifier && '' === trim($impersonatorIdentifier)) {
            throw new \InvalidArgumentException('The audit actor impersonator identifier must not be empty when provided.');
        }
    }
}
