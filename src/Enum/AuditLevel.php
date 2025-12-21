<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Enum;

enum AuditLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
}
