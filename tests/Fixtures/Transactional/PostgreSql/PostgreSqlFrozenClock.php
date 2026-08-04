<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\PostgreSql;

use Psr\Clock\ClockInterface;

final readonly class PostgreSqlFrozenClock implements ClockInterface
{
    public const INSTANT = '2026-08-03T05:06:07.123456+00:00';

    private \DateTimeImmutable $time;

    public function __construct()
    {
        $this->time = new \DateTimeImmutable(self::INSTANT);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->time;
    }
}
