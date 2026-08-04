<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Service;

use Zhortein\AuditableBundle\Message\PersistAuditEntryMessage;
use Zhortein\AuditableBundle\Service\AuditEntryWriterInterface;

final class CollectingAuditEntryWriter implements AuditEntryWriterInterface
{
    /** @var list<PersistAuditEntryMessage> */
    public array $messages = [];

    public function write(PersistAuditEntryMessage $message): void
    {
        $this->messages[] = $message;
    }
}
