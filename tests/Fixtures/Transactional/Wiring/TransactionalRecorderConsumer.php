<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Wiring;

use Zhortein\AuditableBundle\Transactional\Contract\AuditRecorderInterface;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;

final readonly class TransactionalRecorderConsumer
{
    public function __construct(private AuditRecorderInterface $recorder)
    {
    }

    public function record(AuditEvent $event): void
    {
        $this->recorder->record($event);
    }

    public function recorder(): AuditRecorderInterface
    {
        return $this->recorder;
    }
}
