<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Message\PersistAuditEntryMessage;
use Zhortein\AuditableBundle\Service\AsyncAuditEntryWriter;
use Zhortein\AuditableBundle\Service\AuditEntryPersister;
use Zhortein\AuditableBundle\Service\SyncAuditEntryWriter;

final class AuditEntryWriterTest extends TestCase
{
    public function testSyncWriterIsReadonly(): void
    {
        $reflectionClass = new \ReflectionClass(SyncAuditEntryWriter::class);
        self::assertTrue($reflectionClass->isReadonly());
    }

    public function testAsyncWriterIsReadonly(): void
    {
        $reflectionClass = new \ReflectionClass(AsyncAuditEntryWriter::class);
        self::assertTrue($reflectionClass->isReadonly());
    }

    public function testAuditEntryPersisterIsReadonly(): void
    {
        $reflectionClass = new \ReflectionClass(AuditEntryPersister::class);
        self::assertTrue($reflectionClass->isReadonly());
    }

    public function testSyncWriterImplementsInterface(): void
    {
        self::assertTrue(\in_array(
            'Zhortein\AuditableBundle\Service\AuditEntryWriterInterface',
            class_implements(SyncAuditEntryWriter::class)
        ));
    }

    public function testAsyncWriterImplementsInterface(): void
    {
        self::assertTrue(\in_array(
            'Zhortein\AuditableBundle\Service\AuditEntryWriterInterface',
            class_implements(AsyncAuditEntryWriter::class)
        ));
    }

    public function testPersistAuditEntryMessageCanBeCreated(): void
    {
        $message = new PersistAuditEntryMessage(
            occurredAt: (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
            action: 'create',
            level: 'info',
            title: 'Test Entity',
            description: 'A test entity was created',
            context: 'test',
            entityClass: 'App\Entity\Test',
            entityId: '123',
            actorId: '456',
            impersonatorId: null,
            isAuto: true,
            data: ['field' => ['old' => 'a', 'new' => 'b']],
        );

        self::assertSame('create', $message->action);
        self::assertSame('info', $message->level);
        self::assertSame('Test Entity', $message->title);
        self::assertSame('A test entity was created', $message->description);
        self::assertTrue($message->isAuto);
    }

    public function testPersistAuditEntryMessageIsReadonly(): void
    {
        $reflectionClass = new \ReflectionClass(PersistAuditEntryMessage::class);
        self::assertTrue($reflectionClass->isReadonly());
    }
}
