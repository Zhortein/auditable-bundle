<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder;

use Psr\Clock\ClockInterface;

final readonly class FrozenClock implements ClockInterface
{
    public function __construct(
        private \DateTimeImmutable $time,
        private ?CallSequence $sequence = null,
    ) {
    }

    public function now(): \DateTimeImmutable
    {
        $this->sequence?->add('clock');

        return $this->time;
    }
}
