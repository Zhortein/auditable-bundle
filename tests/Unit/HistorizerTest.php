<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zhortein\AuditableBundle\Enum\AuditAction;
use Zhortein\AuditableBundle\Enum\AuditLevel;
use Zhortein\AuditableBundle\Message\PersistAuditEntryMessage;
use Zhortein\AuditableBundle\Service\ActorResolverInterface;
use Zhortein\AuditableBundle\Service\AuditEntryWriterInterface;
use Zhortein\AuditableBundle\Service\Historizer;

final class HistorizerTest extends TestCase
{
    public function testDisabledDoesNotResolveOrWrite(): void
    {
        $writer = $this->createMock(AuditEntryWriterInterface::class);
        $resolver = $this->createMock(ActorResolverInterface::class);
        $writer->expects(self::never())->method('write');
        $resolver->expects(self::never())->method(self::anything());

        (new Historizer($writer, $resolver, $this->createMock(LoggerInterface::class), false))->historize('log', 'title');
    }

    #[DataProvider('messageCases')]
    public function testMessageValuesAreCharacterized(
        AuditAction|string $action,
        AuditLevel|string $level,
        ?object $entity,
        ?string $expectedId,
    ): void {
        $message = null;
        $writer = $this->createMock(AuditEntryWriterInterface::class);
        $writer->expects(self::once())->method('write')->with(self::callback(
            static function (PersistAuditEntryMessage $actual) use (&$message): bool {
                $message = $actual;

                return true;
            }
        ));
        $resolver = $this->createConfiguredMock(ActorResolverInterface::class, [
            'resolveActorId' => 'actor-1',
            'resolveImpersonatorId' => 'admin-2',
        ]);
        $before = new \DateTimeImmutable();

        (new Historizer($writer, $resolver, $this->createMock(LoggerInterface::class)))->historize(
            $action,
            'exact title',
            'exact description',
            $entity,
            'exact-context',
            $level,
            data: ['raw' => ['nested' => true]],
        );
        $after = new \DateTimeImmutable();

        self::assertInstanceOf(PersistAuditEntryMessage::class, $message);
        self::assertSame($action instanceof AuditAction ? $action->value : $action, $message->action);
        self::assertSame($level instanceof AuditLevel ? $level->value : $level, $message->level);
        self::assertSame('exact title', $message->title);
        self::assertSame('exact description', $message->description);
        self::assertSame('exact-context', $message->context);
        self::assertSame(null === $entity ? null : $entity::class, $message->entityClass);
        self::assertSame($expectedId, $message->entityId);
        self::assertSame('actor-1', $message->actorId);
        self::assertSame('admin-2', $message->impersonatorId);
        self::assertFalse($message->isAuto);
        self::assertSame(['raw' => ['nested' => true]], $message->data);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $message->occurredAt);
        $occurredAt = new \DateTimeImmutable($message->occurredAt);
        self::assertGreaterThanOrEqual($before->modify('-1 second'), $occurredAt);
        self::assertLessThanOrEqual($after->modify('+1 second'), $occurredAt);
    }

    /** @return iterable<string, array{AuditAction|string, AuditLevel|string, object|null, string|null}> */
    public static function messageCases(): iterable
    {
        yield 'enum values and integer getId' => [AuditAction::CREATE, AuditLevel::WARNING, new EntityWithId(42), '42'];
        yield 'strings and string getId' => ['custom-action', 'custom-level', new EntityWithId('uuid'), 'uuid'];
        yield 'null getId' => ['log', 'info', new EntityWithId(null), null];
        yield 'no getId' => ['log', 'info', new EntityWithoutId(), null];
        yield 'id method is ignored' => ['log', 'info', new EntityWithIdMethod(), null];
        yield 'Stringable getId' => ['log', 'info', new EntityWithId(new StringableId()), 'stringable-id'];
        yield 'no entity' => ['log', 'info', null, null];
    }

    #[DataProvider('failOpenCases')]
    public function testFailOpenBehavior(string $failure): void
    {
        $writer = $this->createMock(AuditEntryWriterInterface::class);
        $resolver = $this->createMock(ActorResolverInterface::class);
        $entity = null;

        if ('resolver' === $failure) {
            $writer->expects(self::never())->method('write');
            $resolver->expects(self::once())->method('resolveActorId')->willThrowException(new \RuntimeException('resolver failed'));
            $resolver->expects(self::never())->method('resolveImpersonatorId');
        } else {
            $resolver->method('resolveActorId')->willReturn('actor');
            $resolver->method('resolveImpersonatorId')->willReturn(null);
        }
        if ('identifier' === $failure) {
            $writer->expects(self::never())->method('write');
            $resolver->expects(self::never())->method('resolveActorId');
            $resolver->expects(self::never())->method('resolveImpersonatorId');
            $entity = new ThrowingIdEntity();
        }
        if ('writer' === $failure) {
            $writer->expects(self::once())->method('write')->willThrowException(new \RuntimeException('writer failed'));
            $resolver->expects(self::once())->method('resolveActorId');
            $resolver->expects(self::once())->method('resolveImpersonatorId');
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::stringContains('failed'),
            self::callback(static fn (array $context): bool => $context['exception'] instanceof \RuntimeException),
        );

        (new Historizer($writer, $resolver, $logger))->historize('log', 'title', entity: $entity);
    }

    /** @return iterable<string, array{string}> */
    public static function failOpenCases(): iterable
    {
        yield 'resolver exception' => ['resolver'];
        yield 'identifier exception' => ['identifier'];
        yield 'writer exception' => ['writer'];
    }
}

final class EntityWithId
{
    public function __construct(private readonly mixed $id)
    {
    }

    public function getId(): mixed
    {
        return $this->id;
    }
}

final class EntityWithoutId
{
}

final class EntityWithIdMethod
{
    public function id(): int
    {
        return 7;
    }
}

final class StringableId implements \Stringable
{
    public function __toString(): string
    {
        return 'stringable-id';
    }
}

final class ThrowingIdEntity
{
    public function getId(): never
    {
        throw new \RuntimeException('identifier failed');
    }
}
