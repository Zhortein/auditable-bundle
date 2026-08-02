<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Contract;

use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;

/**
 * Deterministically extracts a canonical subject type and identifier.
 *
 * Implementations must fail explicitly for an unidentifiable entity and must
 * never return a partial value.
 */
interface IdentifierExtractorInterface
{
    public function extract(object $entity): AuditSubject;
}
