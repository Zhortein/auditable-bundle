<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit\Transactional\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CallSequence;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturedAuditEntry;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditEntryFactory;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditStorage;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\FrozenClock;
use Zhortein\AuditableBundle\Transactional\Contract\AuditActorResolverInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;
use Zhortein\AuditableBundle\Transactional\Contract\IdentifierExtractorInterface;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;
use Zhortein\AuditableBundle\Transactional\Service\StrictAuditRecorder;

final class StrictAuditRecorderTest extends TestCase
{
    public function testRecordsFullyExplicitEventWithoutConsultingStrategies(): void
    {
        $subject = new AuditSubject('order', '42');
        $actor = new AuditActor('system', 'worker');
        $occurredAt = new \DateTimeImmutable('2026-08-03 12:34:56.123456+05:30');
        [$recorder, $factory, $storage] = $this->capturingRecorder(
            $this->neverExtractor(),
            $this->neverActorResolver(),
            $this->neverClock(),
        );

        $event = new AuditEvent(
            action: 'publish',
            title: 'Order published',
            description: 'Exact description',
            context: 'billing',
            level: 'warning',
            subject: $subject,
            actor: $actor,
            isAuto: true,
            data: ['nested' => ['value' => 42]],
            occurredAt: $occurredAt,
        );
        $result = (new \ReflectionMethod($recorder, 'record'))->invoke($recorder, $event);

        self::assertNull($result);
        self::assertCount(1, $factory->entries);
        self::assertCount(1, $storage->entries);
        self::assertSame($factory->entries[0], $storage->entries[0]);
        $record = $factory->entries[0]->record;
        self::assertSame($occurredAt, $record->occurredAt);
        self::assertSame('publish', $record->action);
        self::assertSame('warning', $record->level);
        self::assertSame('Order published', $record->title);
        self::assertSame('Exact description', $record->description);
        self::assertSame('billing', $record->context);
        self::assertSame($subject, $record->subject);
        self::assertSame($actor, $record->actor);
        self::assertTrue($record->isAuto);
        self::assertSame(['nested' => ['value' => 42]], $record->data);
    }

    public function testExtractsEntityExactlyOnceAndUsesReturnedSubject(): void
    {
        $entity = new \stdClass();
        $subject = new AuditSubject('entity', '7');
        $extractor = $this->createMock(IdentifierExtractorInterface::class);
        $extractor->expects(self::once())->method('extract')->with(self::identicalTo($entity))->willReturn($subject);
        [$recorder, $factory] = $this->capturingRecorder($extractor, $this->neverActorResolver(), $this->neverClock());
        $recorder->record(new AuditEvent('update', 'Updated', entity: $entity, actor: new AuditActor('system'), occurredAt: new \DateTimeImmutable()));
        self::assertSame($subject, $factory->entries[0]->record->subject);
    }

    public function testGlobalEventHasNoSubjectAndNeverUsesExtractor(): void
    {
        [$recorder, $factory] = $this->capturingRecorder($this->neverExtractor(), $this->neverActorResolver(), $this->neverClock());
        $recorder->record(new AuditEvent('run', 'Global', actor: new AuditActor('system'), occurredAt: new \DateTimeImmutable()));
        self::assertNull($factory->entries[0]->record->subject);
    }

    public function testResolvesActorExactlyOnceAndPreservesIdentity(): void
    {
        $actor = new AuditActor('authenticated_user', 'user');
        $resolver = $this->createMock(AuditActorResolverInterface::class);
        $resolver->expects(self::once())->method('resolveActor')->willReturn($actor);
        [$recorder, $factory] = $this->capturingRecorder($this->neverExtractor(), $resolver, $this->neverClock());
        $recorder->record(new AuditEvent('read', 'Read', occurredAt: new \DateTimeImmutable()));
        self::assertSame($actor, $factory->entries[0]->record->actor);
    }

    public function testNullResolvedActorIsValid(): void
    {
        $resolver = $this->createMock(AuditActorResolverInterface::class);
        $resolver->expects(self::once())->method('resolveActor')->willReturn(null);
        [$recorder, $factory] = $this->capturingRecorder($this->neverExtractor(), $resolver, $this->neverClock());
        $recorder->record(new AuditEvent('read', 'Anonymous', occurredAt: new \DateTimeImmutable()));
        self::assertNull($factory->entries[0]->record->actor);
    }

    public function testUsesClockExactlyOnceWithoutChangingTimestamp(): void
    {
        $time = new PreciseDateTimeImmutable('2026-08-03 12:34:56.654321-07:00');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::once())->method('now')->willReturn($time);
        [$recorder, $factory] = $this->capturingRecorder($this->neverExtractor(), $this->neverActorResolver(), $clock);
        $recorder->record(new AuditEvent('run', 'Timed', actor: new AuditActor('system')));
        self::assertSame($time, $factory->entries[0]->record->occurredAt);
        self::assertSame('-07:00', $factory->entries[0]->record->occurredAt->format('P'));
        self::assertSame('654321', $factory->entries[0]->record->occurredAt->format('u'));
    }

    public function testExecutionOrderIsExact(): void
    {
        $sequence = new CallSequence();
        $entity = new \stdClass();
        $extractor = $this->createMock(IdentifierExtractorInterface::class);
        $extractor->method('extract')->willReturnCallback(static function () use ($sequence): AuditSubject {
            $sequence->add('subject');

            return new AuditSubject('entity', '1');
        });
        $resolver = $this->createMock(AuditActorResolverInterface::class);
        $resolver->method('resolveActor')->willReturnCallback(static function () use ($sequence): AuditActor {
            $sequence->add('actor');

            return new AuditActor('system');
        });
        $clock = new FrozenClock(new \DateTimeImmutable(), $sequence);
        $factory = new CapturingAuditEntryFactory($sequence);
        $storage = new CapturingAuditStorage($sequence);
        (new StrictAuditRecorder($extractor, $resolver, $clock, $factory, $storage))->record(new AuditEvent('update', 'Ordered', entity: $entity));
        self::assertSame($factory->entries[0], $storage->entries[0]);
        self::assertSame(['subject', 'actor', 'clock', 'factory', 'storage'], $sequence->calls);
    }

    public function testExtractorFailureStopsAllLaterStepsAndPropagatesSameException(): void
    {
        $exception = new \LogicException('extractor');
        $extractor = $this->createMock(IdentifierExtractorInterface::class);
        $extractor->expects(self::once())->method('extract')->willThrowException($exception);
        $this->assertSameException($exception, new StrictAuditRecorder($extractor, $this->neverActorResolver(), $this->neverClock(), $this->neverFactory(), $this->neverStorage()), new AuditEvent('x', 'X', entity: new \stdClass()));
    }

    public function testActorFailureStopsLaterStepsAndPropagatesSameException(): void
    {
        $exception = new \LogicException('actor');
        $resolver = $this->createMock(AuditActorResolverInterface::class);
        $resolver->expects(self::once())->method('resolveActor')->willThrowException($exception);
        $this->assertSameException($exception, new StrictAuditRecorder($this->neverExtractor(), $resolver, $this->neverClock(), $this->neverFactory(), $this->neverStorage()), new AuditEvent('x', 'X'));
    }

    public function testClockFailureStopsLaterStepsAndPropagatesSameException(): void
    {
        $exception = new \LogicException('clock');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::once())->method('now')->willThrowException($exception);
        $this->assertSameException($exception, new StrictAuditRecorder($this->neverExtractor(), $this->nullActorResolver(), $clock, $this->neverFactory(), $this->neverStorage()), new AuditEvent('x', 'X'));
    }

    public function testFactoryFailureStopsStorageAndPropagatesSameException(): void
    {
        $exception = new \LogicException('factory');
        $factory = $this->createMock(AuditEntryFactoryInterface::class);
        $factory->expects(self::once())->method('create')->willThrowException($exception);
        $this->assertSameException($exception, new StrictAuditRecorder($this->neverExtractor(), $this->neverActorResolver(), $this->neverClock(), $factory, $this->neverStorage()), new AuditEvent('x', 'X', actor: new AuditActor('system'), occurredAt: new \DateTimeImmutable()));
    }

    public function testStorageFailurePropagatesSameException(): void
    {
        $exception = new \LogicException('storage');
        $entry = new \stdClass();
        $factory = $this->createMock(AuditEntryFactoryInterface::class);
        $factory->expects(self::once())->method('create')->willReturn($entry);
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage->expects(self::once())->method('persist')->with(self::identicalTo($entry))->willThrowException($exception);
        $this->assertSameException($exception, new StrictAuditRecorder($this->neverExtractor(), $this->neverActorResolver(), $this->neverClock(), $factory, $storage), new AuditEvent('x', 'X', actor: new AuditActor('system'), occurredAt: new \DateTimeImmutable()));
    }

    public function testExplicitValuesBypassFailingStrategies(): void
    {
        $subject = new AuditSubject('explicit', '1');
        $actor = new AuditActor('explicit', '2');
        $time = new \DateTimeImmutable('2026-01-01T00:00:00+02:00');
        [$recorder, $factory] = $this->capturingRecorder($this->neverExtractor(), $this->neverActorResolver(), $this->neverClock());
        $recorder->record(new AuditEvent('x', 'X', subject: $subject, actor: $actor, occurredAt: $time));
        self::assertSame($subject, $factory->entries[0]->record->subject);
        self::assertSame($actor, $factory->entries[0]->record->actor);
        self::assertSame($time, $factory->entries[0]->record->occurredAt);
    }

    /** @return array{StrictAuditRecorder<CapturedAuditEntry>, CapturingAuditEntryFactory, CapturingAuditStorage} */
    private function capturingRecorder(IdentifierExtractorInterface $extractor, AuditActorResolverInterface $resolver, ClockInterface $clock): array
    {
        $factory = new CapturingAuditEntryFactory();
        $storage = new CapturingAuditStorage();

        return [new StrictAuditRecorder($extractor, $resolver, $clock, $factory, $storage), $factory, $storage];
    }

    private function neverExtractor(): IdentifierExtractorInterface&MockObject
    {
        $mock = $this->createMock(IdentifierExtractorInterface::class);
        $mock->expects(self::never())->method('extract');

        return $mock;
    }

    private function neverActorResolver(): AuditActorResolverInterface&MockObject
    {
        $mock = $this->createMock(AuditActorResolverInterface::class);
        $mock->expects(self::never())->method('resolveActor');

        return $mock;
    }

    private function nullActorResolver(): AuditActorResolverInterface&MockObject
    {
        $mock = $this->createMock(AuditActorResolverInterface::class);
        $mock->expects(self::once())->method('resolveActor')->willReturn(null);

        return $mock;
    }

    private function neverClock(): ClockInterface&MockObject
    {
        $mock = $this->createMock(ClockInterface::class);
        $mock->expects(self::never())->method('now');

        return $mock;
    }

    /** @return AuditEntryFactoryInterface<object>&MockObject */
    private function neverFactory(): AuditEntryFactoryInterface&MockObject
    {
        $mock = $this->createMock(AuditEntryFactoryInterface::class);
        $mock->expects(self::never())->method('create');

        return $mock;
    }

    /** @return AuditStorageInterface<object>&MockObject */
    private function neverStorage(): AuditStorageInterface&MockObject
    {
        $mock = $this->createMock(AuditStorageInterface::class);
        $mock->expects(self::never())->method('persist');

        return $mock;
    }

    /** @param StrictAuditRecorder<object> $recorder */
    private function assertSameException(\Throwable $expected, StrictAuditRecorder $recorder, AuditEvent $event): void
    {
        try {
            $recorder->record($event);
            self::fail('Recording should have failed.');
        } catch (\Throwable $actual) {
            self::assertSame($expected, $actual);
        }
    }
}

final class PreciseDateTimeImmutable extends \DateTimeImmutable
{
}
