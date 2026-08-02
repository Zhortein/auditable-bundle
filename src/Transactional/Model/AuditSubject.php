<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Model;

/**
 * Identifies the subject of an audit event.
 *
 * The type can be an FQCN or a logical type. The identifier is the canonical,
 * deterministic representation supplied by an extractor; this value object
 * deliberately imposes no representation format yet.
 */
final readonly class AuditSubject
{
    public function __construct(
        public string $type,
        public string $identifier,
    ) {
        if ('' === trim($type)) {
            throw new \InvalidArgumentException('The audit subject type must not be empty.');
        }
        if ('' === trim($identifier)) {
            throw new \InvalidArgumentException('The audit subject identifier must not be empty.');
        }
    }
}
