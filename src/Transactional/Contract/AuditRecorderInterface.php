<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Contract;

use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;

/**
 * Strict recorder contract: errors must not be absorbed.
 *
 * A void return does not mean that a transaction has already committed. This
 * contract imposes neither a flush nor transaction management.
 */
interface AuditRecorderInterface
{
    public function record(AuditEvent $event): void;
}
