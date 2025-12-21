<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Enum;

enum AuditAction: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case LOG = 'log';
}
