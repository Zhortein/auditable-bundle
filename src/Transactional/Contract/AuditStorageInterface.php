<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Contract;

/**
 * Prepares or attaches an entry to the current storage context.
 *
 * Storage must not flush, begin, commit, or roll back a transaction, and must
 * not absorb errors. Returning does not guarantee that a commit occurred.
 *
 * @template TEntry of object
 */
interface AuditStorageInterface
{
    /** @param TEntry $entry */
    public function persist(object $entry): void;
}
