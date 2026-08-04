<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit\Transactional\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Model\AuditRecord;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;

final class AuditRecordTest extends TestCase
{
    public function testPreservesCompleteRecord(): void
    {
        $time = new \DateTimeImmutable('2026-01-02T03:04:05+00:00');
        $subject = new AuditSubject('order', '42');
        $actor = new AuditActor('user', '7');
        $data = ['key' => 'value'];
        $record = new AuditRecord($time, 'update', 'warning', 'Title', 'Description', 'orders', $subject, $actor, true, $data);
        self::assertSame($time, $record->occurredAt);
        self::assertSame('update', $record->action);
        self::assertSame('warning', $record->level);
        self::assertSame('Title', $record->title);
        self::assertSame('Description', $record->description);
        self::assertSame('orders', $record->context);
        self::assertSame($subject, $record->subject);
        self::assertSame($actor, $record->actor);
        self::assertTrue($record->isAuto);
        self::assertSame($data, $record->data);
    }

    public function testAllowsRecordWithoutSubjectOrActorAndAcceptsZero(): void
    {
        $record = new AuditRecord(new \DateTimeImmutable(), '0', '0', '0');
        self::assertNull($record->subject);
        self::assertNull($record->actor);
        self::assertSame([], $record->data);
    }

    #[DataProvider('invalidText')]
    public function testRejectsInvalidText(string $action, string $level, string $title): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuditRecord(new \DateTimeImmutable(), $action, $level, $title);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidText(): iterable
    {
        yield 'empty action' => ['', 'info', 'title'];
        yield 'blank action' => ['  ', 'info', 'title'];
        yield 'empty level' => ['log', '', 'title'];
        yield 'blank level' => ['log', '  ', 'title'];
        yield 'empty title' => ['log', 'info', ''];
        yield 'blank title' => ['log', 'info', '  '];
    }

    public function testIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(AuditRecord::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertFalse($reflection->hasProperty('entity'));
    }
}
