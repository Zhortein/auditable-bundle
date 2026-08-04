<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit\Transactional\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Enum\AuditAction;
use Zhortein\AuditableBundle\Enum\AuditLevel;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;

final class AuditEventTest extends TestCase
{
    public function testNormalizesEnumsAndPreservesAllNominalData(): void
    {
        $entity = new \stdClass();
        $actor = new AuditActor('user', '7');
        $time = new \DateTimeImmutable('2026-01-02T03:04:05+00:00');
        $data = ['changed' => ['before', 'after']];
        $event = new AuditEvent(AuditAction::UPDATE, 'Title', 'Description', 'orders', AuditLevel::WARNING, $entity, null, $actor, true, $data, $time);
        self::assertSame('update', $event->action);
        self::assertSame('warning', $event->level);
        self::assertSame('Title', $event->title);
        self::assertSame('Description', $event->description);
        self::assertSame('orders', $event->context);
        self::assertSame($entity, $event->entity);
        self::assertNull($event->subject);
        self::assertSame($actor, $event->actor);
        self::assertTrue($event->isAuto);
        self::assertSame($data, $event->data);
        self::assertSame($time, $event->occurredAt);
    }

    public function testPreservesCustomStringsAndExplicitSubject(): void
    {
        $subject = new AuditSubject('order', '42');
        $event = new AuditEvent('custom-action', 'Custom', subject: $subject, level: 'notice');
        self::assertSame('custom-action', $event->action);
        self::assertSame('notice', $event->level);
        self::assertSame($subject, $event->subject);
        self::assertNull($event->entity);
    }

    public function testGlobalEventDefaults(): void
    {
        $event = new AuditEvent('0', '0', level: '0');
        self::assertSame('0', $event->action);
        self::assertSame('0', $event->title);
        self::assertSame('0', $event->level);
        self::assertNull($event->description);
        self::assertNull($event->context);
        self::assertNull($event->entity);
        self::assertNull($event->subject);
        self::assertNull($event->actor);
        self::assertFalse($event->isAuto);
        self::assertSame([], $event->data);
        self::assertNull($event->occurredAt);
    }

    #[DataProvider('invalidText')]
    public function testRejectsEmptyActionLevelOrTitle(string $action, string $title, string $level): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuditEvent($action, $title, level: $level);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidText(): iterable
    {
        yield 'empty action' => ['', 'title', 'info'];
        yield 'blank action' => ['  ', 'title', 'info'];
        yield 'empty level' => ['log', 'title', ''];
        yield 'blank level' => ['log', 'title', '  '];
        yield 'empty title' => ['log', '', 'info'];
        yield 'blank title' => ['log', '  ', 'info'];
    }

    public function testRejectsEntityAndSubjectTogether(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuditEvent('log', 'title', entity: new \stdClass(), subject: new AuditSubject('type', 'id'));
    }

    public function testIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(AuditEvent::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }
}
