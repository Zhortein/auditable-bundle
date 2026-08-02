<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Zhortein\AuditableBundle\Entity\AuditEntry;
use Zhortein\AuditableBundle\Message\PersistAuditEntryMessage;
use Zhortein\AuditableBundle\MessageHandler\PersistAuditEntryMessageHandler;
use Zhortein\AuditableBundle\Service\AsyncAuditEntryWriter;
use Zhortein\AuditableBundle\Service\AuditEntryPersister;
use Zhortein\AuditableBundle\Service\SyncAuditEntryWriter;

final class PersistencePipelineTest extends TestCase
{
    public function testAsyncWriterDispatchesSameMessageOnceWithoutStamp(): void
    {
        $message = self::message();
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(
            self::identicalTo($message),
            self::identicalTo([]),
        )->willReturn(new Envelope($message));

        (new AsyncAuditEntryWriter($bus))->write($message);
    }

    public function testSyncWriterPersistsMessageExactlyOnce(): void
    {
        $message = self::message();
        $entityManager = $this->entityManagerExpectingPersistThenFlush($message);

        (new SyncAuditEntryWriter(new AuditEntryPersister($entityManager)))->write($message);
    }

    public function testPersisterCopiesEveryFieldAndPersistsBeforeFlush(): void
    {
        $message = self::message();
        (new AuditEntryPersister($this->entityManagerExpectingPersistThenFlush($message)))->persist($message);
    }

    public function testHandlerIsInvocableAndDelegatesOnce(): void
    {
        $message = self::message();
        $handler = new PersistAuditEntryMessageHandler(
            new AuditEntryPersister($this->entityManagerExpectingPersistThenFlush($message)),
        );

        self::assertIsCallable($handler);
        $handler($message);
    }

    private function entityManagerExpectingPersistThenFlush(PersistAuditEntryMessage $message): EntityManagerInterface
    {
        $sequence = 0;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            static function (AuditEntry $entry) use ($message, &$sequence): bool {
                self::assertSame(0, $sequence++);
                self::assertSame($message->occurredAt, $entry->getOccurredAt()->format(\DateTimeInterface::RFC3339_EXTENDED));
                self::assertSame($message->action, $entry->getAction());
                self::assertSame($message->level, $entry->getLevel());
                self::assertSame($message->title, $entry->getTitle());
                self::assertSame($message->description, $entry->getDescription());
                self::assertSame($message->context, $entry->getContext());
                self::assertSame($message->entityClass, $entry->getEntityClass());
                self::assertSame($message->entityId, $entry->getEntityId());
                self::assertSame($message->actorId, $entry->getActorId());
                self::assertSame($message->impersonatorId, $entry->getImpersonatorId());
                self::assertSame($message->isAuto, $entry->isAuto());
                self::assertSame($message->data, $entry->getData());

                return true;
            }
        ));
        $entityManager->expects(self::once())->method('flush')->with()->willReturnCallback(
            static function () use (&$sequence): void {
                self::assertSame(1, $sequence++);
            }
        );

        return $entityManager;
    }

    private static function message(): PersistAuditEntryMessage
    {
        return new PersistAuditEntryMessage(
            '2025-12-21T10:11:12.123+00:00',
            'update',
            'warning',
            'Title',
            'Description',
            'context',
            'Example\\Entity',
            '42',
            'actor',
            'impersonator',
            true,
            ['field' => ['old' => 'before', 'new' => 'after']],
        );
    }
}
