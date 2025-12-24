<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Enum;

/**
 * Enumerates severity levels for audit log entries.
 *
 * Similar to PSR-3 LogLevel, these levels help categorize and filter audit events
 * by their importance or severity.
 *
 * @see AuditEntry for how these levels are stored in the audit trail.
 */
enum AuditLevel: string
{
    /**
     * Debug level.
     *
     * For detailed diagnostic information, typically used during development.
     */
    case DEBUG = 'debug';

    /**
     * Informational level.
     *
     * Default level. Used for normal business operations (entity create/update/delete).
     */
    case INFO = 'info';

    /**
     * Warning level.
     *
     * Something unexpected happened but the operation succeeded (e.g., uncommon data state).
     */
    case WARNING = 'warning';

    /**
     * Error level.
     *
     * Something went wrong but auditing continued (e.g., validation failure, access denied).
     */
    case ERROR = 'error';

    /**
     * Critical level.
     *
     * A severe condition requiring immediate attention (e.g., security breach attempt).
     */
    case CRITICAL = 'critical';
}
