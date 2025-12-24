<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Enum;

/**
 * Enumerates the types of audit actions that can be recorded.
 *
 * @see AuditEntry for how these actions are stored in the audit trail.
 */
enum AuditAction: string
{
    /**
     * Entity creation action.
     *
     * Triggered when a new auditable entity is persisted to the database.
     */
    case CREATE = 'create';

    /**
     * Entity update action.
     *
     * Triggered when an auditable entity's properties are modified and flushed to the database.
     */
    case UPDATE = 'update';

    /**
     * Entity deletion action.
     *
     * Triggered when an auditable entity is removed from the database.
     */
    case DELETE = 'delete';

    /**
     * Manual log action.
     *
     * Used for explicit audit log entries created programmatically via the Historizer service.
     */
    case LOG = 'log';
}
