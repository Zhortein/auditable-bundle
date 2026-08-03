<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder;

final class CallSequence
{
    /** @var list<string> */
    public array $calls = [];

    public function add(string $call): void
    {
        $this->calls[] = $call;
    }
}
