<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Contract;

use Zhortein\AuditableBundle\Transactional\Model\AuditRecord;

/**
 * Creates, but does not persist, a persistable audit representation.
 *
 * The factory performs no flush and commits no transaction.
 *
 * @template TEntry of object
 */
interface AuditEntryFactoryInterface
{
    /** @return TEntry */
    public function create(AuditRecord $record): object;
}
