<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditActorResolverInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditRecorderInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;
use Zhortein\AuditableBundle\Transactional\Contract\IdentifierExtractorInterface;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;
use Zhortein\AuditableBundle\Transactional\Service\StrictAuditRecorder;

final class StrictAuditRecorderContractTest extends TestCase
{
    public function testRecorderContractIsExact(): void
    {
        self::assertTrue(class_exists(StrictAuditRecorder::class));
        $reflection = new \ReflectionClass(StrictAuditRecorder::class);
        self::assertSame('Zhortein\\AuditableBundle\\Transactional\\Service', $reflection->getNamespaceName());
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->implementsInterface(AuditRecorderInterface::class));
        self::assertStringContainsString('@template TEntry of object', (string) $reflection->getDocComment());
        self::assertSame([], $reflection->getConstants(\ReflectionClassConstant::IS_PUBLIC));

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
        $parameters = $constructor->getParameters();
        self::assertSame(
            ['identifierExtractor', 'actorResolver', 'clock', 'entryFactory', 'storage'],
            array_map(static fn (\ReflectionParameter $parameter): string => $parameter->getName(), $parameters),
        );
        self::assertSame(
            [IdentifierExtractorInterface::class, AuditActorResolverInterface::class, ClockInterface::class, AuditEntryFactoryInterface::class, AuditStorageInterface::class],
            array_map(static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(), $parameters),
        );
        foreach ($parameters as $parameter) {
            self::assertTrue($parameter->isPromoted());
            self::assertTrue($reflection->getProperty($parameter->getName())->isPrivate());
        }
        $constructorDoc = (string) $constructor->getDocComment();
        self::assertMatchesRegularExpression('/@param AuditEntryFactoryInterface<TEntry>\s+\$entryFactory/', $constructorDoc);
        self::assertMatchesRegularExpression('/@param AuditStorageInterface<TEntry>\s+\$storage/', $constructorDoc);

        $publicMethods = array_values(array_filter(
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            static fn (\ReflectionMethod $method): bool => StrictAuditRecorder::class === $method->getDeclaringClass()->getName(),
        ));
        self::assertSame(['__construct', 'record'], array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $publicMethods));
        $record = $reflection->getMethod('record');
        self::assertSame(AuditEvent::class, (string) $record->getParameters()[0]->getType());
        self::assertSame('void', (string) $record->getReturnType());
    }

    public function testPsrClockIsADirectRuntimeDependency(): void
    {
        $composer = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $require = $composer['require'] ?? null;
        $requireDev = $composer['require-dev'] ?? null;
        self::assertIsArray($require);
        self::assertIsArray($requireDev);
        self::assertSame('^1.0', $require['psr/clock'] ?? null);
        self::assertArrayNotHasKey('symfony/clock', $require);
        self::assertArrayNotHasKey('symfony/clock', $requireDev);
    }
}
