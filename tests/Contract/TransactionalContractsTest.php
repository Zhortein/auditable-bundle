<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Transactional\Contract\AuditActorResolverInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditEntryFactoryInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditRecorderInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditStorageInterface;
use Zhortein\AuditableBundle\Transactional\Contract\IdentifierExtractorInterface;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;
use Zhortein\AuditableBundle\Transactional\Model\AuditRecord;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;

final class TransactionalContractsTest extends TestCase
{
    /** @var array<class-string, list<array{name: string, type: string, optional: bool, default: mixed}>> */
    private const CONSTRUCTORS = [
        AuditSubject::class => [
            ['name' => 'type', 'type' => 'string', 'optional' => false, 'default' => null],
            ['name' => 'identifier', 'type' => 'string', 'optional' => false, 'default' => null],
        ],
        AuditActor::class => [
            ['name' => 'type', 'type' => 'string', 'optional' => false, 'default' => null],
            ['name' => 'identifier', 'type' => '?string', 'optional' => true, 'default' => null],
            ['name' => 'impersonatorIdentifier', 'type' => '?string', 'optional' => true, 'default' => null],
            ['name' => 'metadata', 'type' => 'array', 'optional' => true, 'default' => []],
        ],
        AuditEvent::class => [
            ['name' => 'action', 'type' => 'Zhortein\\AuditableBundle\\Enum\\AuditAction|string', 'optional' => false, 'default' => null],
            ['name' => 'title', 'type' => 'string', 'optional' => false, 'default' => null],
            ['name' => 'description', 'type' => '?string', 'optional' => true, 'default' => null],
            ['name' => 'context', 'type' => '?string', 'optional' => true, 'default' => null],
            ['name' => 'level', 'type' => 'Zhortein\\AuditableBundle\\Enum\\AuditLevel|string', 'optional' => true, 'default' => 'info'],
            ['name' => 'entity', 'type' => '?object', 'optional' => true, 'default' => null],
            ['name' => 'subject', 'type' => '?Zhortein\\AuditableBundle\\Transactional\\Model\\AuditSubject', 'optional' => true, 'default' => null],
            ['name' => 'actor', 'type' => '?Zhortein\\AuditableBundle\\Transactional\\Model\\AuditActor', 'optional' => true, 'default' => null],
            ['name' => 'isAuto', 'type' => 'bool', 'optional' => true, 'default' => false],
            ['name' => 'data', 'type' => 'array', 'optional' => true, 'default' => []],
            ['name' => 'occurredAt', 'type' => '?DateTimeImmutable', 'optional' => true, 'default' => null],
        ],
        AuditRecord::class => [
            ['name' => 'occurredAt', 'type' => 'DateTimeImmutable', 'optional' => false, 'default' => null],
            ['name' => 'action', 'type' => 'string', 'optional' => false, 'default' => null],
            ['name' => 'level', 'type' => 'string', 'optional' => false, 'default' => null],
            ['name' => 'title', 'type' => 'string', 'optional' => false, 'default' => null],
            ['name' => 'description', 'type' => '?string', 'optional' => true, 'default' => null],
            ['name' => 'context', 'type' => '?string', 'optional' => true, 'default' => null],
            ['name' => 'subject', 'type' => '?Zhortein\\AuditableBundle\\Transactional\\Model\\AuditSubject', 'optional' => true, 'default' => null],
            ['name' => 'actor', 'type' => '?Zhortein\\AuditableBundle\\Transactional\\Model\\AuditActor', 'optional' => true, 'default' => null],
            ['name' => 'isAuto', 'type' => 'bool', 'optional' => true, 'default' => false],
            ['name' => 'data', 'type' => 'array', 'optional' => true, 'default' => []],
        ],
    ];

    /** @var array<class-string, array<string, array{parameter: string, type: string, return: string}>> */
    private const INTERFACES = [
        AuditRecorderInterface::class => ['record' => ['parameter' => 'event', 'type' => AuditEvent::class, 'return' => 'void']],
        IdentifierExtractorInterface::class => ['extract' => ['parameter' => 'entity', 'type' => 'object', 'return' => AuditSubject::class]],
        AuditActorResolverInterface::class => ['resolveActor' => ['parameter' => '', 'type' => '', 'return' => '?'.AuditActor::class]],
        AuditEntryFactoryInterface::class => ['create' => ['parameter' => 'record', 'type' => AuditRecord::class, 'return' => 'object']],
        AuditStorageInterface::class => ['persist' => ['parameter' => 'entry', 'type' => 'object', 'return' => 'void']],
    ];

    public function testExactModelContracts(): void
    {
        foreach (self::CONSTRUCTORS as $class => $expectedParameters) {
            $reflection = new \ReflectionClass($class);
            self::assertSame('Zhortein\\AuditableBundle\\Transactional\\Model', $reflection->getNamespaceName());
            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());
            $constructor = $reflection->getConstructor();
            self::assertNotNull($constructor);
            self::assertTrue($constructor->isPublic());
            self::assertFalse($constructor->isStatic());
            self::assertSame($expectedParameters, self::parameters($constructor));
            foreach ($constructor->getParameters() as $parameter) {
                self::assertFalse($parameter->isPassedByReference());
                self::assertFalse($parameter->isVariadic());
            }

            $declaredMethods = array_filter(
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class,
            );
            self::assertSame(['__construct'], array_values(array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $declaredMethods)));

            $expectedProperties = array_column($expectedParameters, 'type', 'name');
            if (AuditEvent::class === $class) {
                $expectedProperties['action'] = 'string';
                $expectedProperties['level'] = 'string';
            }
            $actualProperties = [];
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->getDeclaringClass()->getName() === $class) {
                    $actualProperties[$property->getName()] = self::type($property->getType());
                }
            }
            ksort($expectedProperties);
            ksort($actualProperties);
            self::assertSame($expectedProperties, $actualProperties);
        }
    }

    public function testExactInterfaceContracts(): void
    {
        foreach (self::INTERFACES as $interface => $expectedMethods) {
            $reflection = new \ReflectionClass($interface);
            self::assertSame('Zhortein\\AuditableBundle\\Transactional\\Contract', $reflection->getNamespaceName());
            self::assertTrue($reflection->isInterface());
            self::assertFalse($reflection->isFinal());
            $actualMethods = [];
            foreach ($reflection->getMethods() as $method) {
                self::assertTrue($method->isPublic());
                self::assertFalse($method->isStatic());
                $parameters = $method->getParameters();
                self::assertCount('' === $expectedMethods[$method->getName()]['parameter'] ? 0 : 1, $parameters);
                foreach ($parameters as $parameter) {
                    self::assertFalse($parameter->isPassedByReference());
                    self::assertFalse($parameter->isVariadic());
                }
                $actualMethods[$method->getName()] = [
                    'parameter' => isset($parameters[0]) ? $parameters[0]->getName() : '',
                    'type' => isset($parameters[0]) ? self::type($parameters[0]->getType()) : '',
                    'return' => (string) self::type($method->getReturnType()),
                ];
            }
            self::assertSame($expectedMethods, $actualMethods);
        }
        self::assertSame(['record'], get_class_methods(AuditRecorderInterface::class));
        self::assertSame(['create'], get_class_methods(AuditEntryFactoryInterface::class));
        self::assertSame(['persist'], get_class_methods(AuditStorageInterface::class));
        foreach (['flush', 'commit', 'rollback', 'transaction'] as $forbidden) {
            self::assertFalse(method_exists(AuditStorageInterface::class, $forbidden));
        }
    }

    /** @return list<array{name: string, type: string, optional: bool, default: mixed}> */
    private static function parameters(\ReflectionMethod $method): array
    {
        return array_map(static fn (\ReflectionParameter $parameter): array => [
            'name' => $parameter->getName(),
            'type' => (string) self::type($parameter->getType()),
            'optional' => $parameter->isOptional(),
            'default' => $parameter->isDefaultValueAvailable() ? self::defaultValue($parameter) : null,
        ], $method->getParameters());
    }

    private static function defaultValue(\ReflectionParameter $parameter): mixed
    {
        $value = $parameter->getDefaultValue();

        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    private static function type(?\ReflectionType $type): ?string
    {
        if (null === $type) {
            return null;
        }
        if ($type instanceof \ReflectionNamedType) {
            return ($type->allowsNull() && 'mixed' !== $type->getName() ? '?' : '').$type->getName();
        }
        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(self::type(...), $type->getTypes()));
        }
        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map(self::type(...), $type->getTypes()));
        }

        throw new \LogicException('Unsupported reflection type.');
    }
}
