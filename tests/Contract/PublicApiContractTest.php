<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type PublicParameter array{name: string, type: ?string, by_reference: bool, variadic: bool, has_default: bool, default: mixed}
 * @phpstan-type PublicMethod array{static: bool, return: ?string, parameters: list<PublicParameter>}
 * @phpstan-type PublicProperty array{type: ?string, readonly: bool, static: bool}
 * @phpstan-type PublicContract array{
 *     kind: 'class'|'interface'|'enum',
 *     final: bool,
 *     readonly: bool,
 *     attribute_targets: ?int,
 *     enum_cases: array<string, mixed>,
 *     public_properties: array<string, PublicProperty>,
 *     public_methods: array<string, PublicMethod>
 * }
 * @phpstan-type PublicApi array<string, PublicContract>
 */
final class PublicApiContractTest extends TestCase
{
    private const SNAPSHOT = __DIR__.'/public-api-1.0.0.json';

    public function testPublicApiMatchesVersionOneSnapshot(): void
    {
        $actual = self::publicApi();
        self::assertFileExists(self::SNAPSHOT);
        $expected = json_decode((string) file_get_contents(self::SNAPSHOT), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($expected);
        /** @var PublicApi $typedExpected */
        $typedExpected = $expected;

        self::assertHistoricalContractIsPreserved($typedExpected, $actual);
    }

    public function testAdditionalPublicTypesAndMembersAreAllowed(): void
    {
        $historical = [
            'Legacy\\PublicType' => self::contractFixture(),
        ];
        $current = $historical;
        $current['Future\\TransactionalType'] = self::contractFixture();
        $current['Legacy\\PublicType']['public_methods']['futureMethod'] = [
            'static' => false,
            'return' => 'void',
            'parameters' => [],
        ];
        $current['Legacy\\PublicType']['public_properties']['futureProperty'] = [
            'type' => 'string',
            'readonly' => true,
            'static' => false,
        ];

        self::assertHistoricalContractIsPreserved($historical, $current);
        $reflection = new \ReflectionClass(CanonicalTypeChild::class);

        self::assertSame('self', self::type($reflection->getMethod('selfType')->getReturnType(), $reflection));
        self::assertSame('parent', self::type($reflection->getMethod('parentType')->getReturnType(), $reflection));
        self::assertSame('static', self::type($reflection->getMethod('staticType')->getReturnType(), $reflection));
        self::assertSame('?self', self::type($reflection->getMethod('nullableSelfType')->getReturnType(), $reflection));
        self::assertSame('self|string', self::type($reflection->getMethod('unionType')->getReturnType(), $reflection));
        self::assertSame(
            CanonicalTypeLeft::class.'&'.CanonicalTypeRight::class,
            self::type($reflection->getMethod('intersectionType')->getReturnType(), $reflection),
        );
    }

    /** @param PublicApi $current */
    #[DataProvider('brokenHistoricalContracts')]
    public function testMissingOrModifiedHistoricalContractIsRejected(array $current): void
    {
        $historical = ['Legacy\\PublicType' => self::contractFixture()];

        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        self::assertHistoricalContractIsPreserved($historical, $current);
    }

    /** @return iterable<string, array{PublicApi}> */
    public static function brokenHistoricalContracts(): iterable
    {
        yield 'missing historical type' => [[]];

        $modified = self::contractFixture();
        $modified['final'] = false;
        yield 'modified historical type' => [['Legacy\\PublicType' => $modified]];
    }

    /** @return PublicApi */
    public static function publicApi(): array
    {
        $classes = [];
        foreach (self::sourceClasses() as $class) {
            $reflection = new \ReflectionClass($class);
            $methods = [];
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $parameters = [];
                foreach ($method->getParameters() as $parameter) {
                    $parameters[] = [
                        'name' => $parameter->getName(),
                        'type' => self::type($parameter->getType(), $reflection),
                        'by_reference' => $parameter->isPassedByReference(),
                        'variadic' => $parameter->isVariadic(),
                        'has_default' => $parameter->isDefaultValueAvailable(),
                        'default' => $parameter->isDefaultValueAvailable() ? self::value($parameter->getDefaultValue()) : null,
                    ];
                }
                $methods[$method->getName()] = [
                    'static' => $method->isStatic(),
                    'return' => self::type($method->getReturnType(), $reflection),
                    'parameters' => $parameters,
                ];
            }
            ksort($methods);

            $properties = [];
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $properties[$property->getName()] = [
                    'type' => self::type($property->getType(), $reflection),
                    'readonly' => $property->isReadOnly(),
                    'static' => $property->isStatic(),
                ];
            }
            ksort($properties);

            $enumCases = [];
            if ($reflection->isEnum()) {
                foreach ($reflection->getReflectionConstants() as $case) {
                    if ($case->isEnumCase()) {
                        $enumCases[$case->getName()] = self::value($case->getValue());
                    }
                }
            }
            $classes[$class] = [
                'kind' => $reflection->isInterface() ? 'interface' : ($reflection->isEnum() ? 'enum' : 'class'),
                'final' => $reflection->isFinal(),
                'readonly' => $reflection->isReadOnly(),
                'attribute_targets' => self::attributeTargets($reflection),
                'enum_cases' => $enumCases,
                'public_properties' => $properties,
                'public_methods' => $methods,
            ];
        }
        ksort($classes);

        return $classes;
    }

    /**
     * @param PublicApi $historical
     * @param PublicApi $current
     */
    public static function assertHistoricalContractIsPreserved(array $historical, array $current): void
    {
        foreach ($historical as $class => $expectedContract) {
            self::assertArrayHasKey($class, $current, \sprintf('Historical public type %s no longer exists.', $class));
            $actualContract = $current[$class];

            foreach (['kind', 'final', 'readonly', 'attribute_targets', 'enum_cases'] as $key) {
                self::assertSame($expectedContract[$key], $actualContract[$key], \sprintf('%s changed for %s.', $key, $class));
            }
            foreach (['public_methods', 'public_properties'] as $membersKey) {
                foreach ($expectedContract[$membersKey] as $member => $expectedMember) {
                    self::assertArrayHasKey($member, $actualContract[$membersKey], \sprintf('%s::%s no longer has its historical public visibility.', $class, $member));
                    self::assertSame($expectedMember, $actualContract[$membersKey][$member], \sprintf('Public contract of %s::%s changed.', $class, $member));
                }
            }
        }
    }

    /** @return PublicContract */
    private static function contractFixture(): array
    {
        return [
            'kind' => 'class',
            'final' => true,
            'readonly' => false,
            'attribute_targets' => null,
            'enum_cases' => [],
            'public_properties' => [],
            'public_methods' => [],
        ];
    }

    /** @return list<class-string> */
    private static function sourceClasses(): array
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 2).'/src')) as $file) {
            if ($file instanceof \SplFileInfo && 'php' === $file->getExtension()) {
                require_once $file->getPathname();
            }
        }

        $sourceDirectory = realpath(\dirname(__DIR__, 2).'/src');
        self::assertIsString($sourceDirectory);
        $symbols = array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits());
        $symbols = array_values(array_filter(
            $symbols,
            static function (string $symbol) use ($sourceDirectory): bool {
                if (!str_starts_with($symbol, 'Zhortein\\AuditableBundle\\')) {
                    return false;
                }
                $filename = (new \ReflectionClass($symbol))->getFileName();

                return \is_string($filename) && str_starts_with(realpath($filename) ?: '', $sourceDirectory.'/');
            },
        ));
        sort($symbols);

        /* @var list<class-string> $symbols */
        return $symbols;
    }

    /** @param \ReflectionClass<object> $declaringClass */
    private static function type(?\ReflectionType $type, \ReflectionClass $declaringClass): ?string
    {
        if (null === $type) {
            return null;
        }
        if ($type instanceof \ReflectionNamedType) {
            $name = $type->getName();
            if ($name === $declaringClass->getName()) {
                $name = 'self';
            } elseif (false !== $declaringClass->getParentClass() && $name === $declaringClass->getParentClass()->getName()) {
                $name = 'parent';
            }

            return ($type->allowsNull() && 'mixed' !== $name ? '?' : '').$name;
        }
        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(static fn (\ReflectionType $member): ?string => self::type($member, $declaringClass), $type->getTypes()));
        }
        if (!$type instanceof \ReflectionIntersectionType) {
            throw new \LogicException('Unsupported reflection type.');
        }

        return implode('&', array_map(static fn (\ReflectionType $member): ?string => self::type($member, $declaringClass), $type->getTypes()));
    }

    private static function value(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return $value;
    }

    /** @param \ReflectionClass<object> $reflection */
    private static function attributeTargets(\ReflectionClass $reflection): ?int
    {
        $attributes = $reflection->getAttributes(\Attribute::class);

        return [] === $attributes ? null : $attributes[0]->newInstance()->flags;
    }
}

interface CanonicalTypeLeft
{
}

interface CanonicalTypeRight
{
}

class CanonicalTypeParent
{
}

final class CanonicalTypeChild extends CanonicalTypeParent
{
    public function selfType(): self
    {
        throw new \LogicException();
    }

    public function parentType(): parent
    {
        throw new \LogicException();
    }

    public function staticType(): static
    {
        throw new \LogicException();
    }

    public function nullableSelfType(): ?self
    {
        throw new \LogicException();
    }

    public function unionType(): self|string
    {
        throw new \LogicException();
    }

    public function intersectionType(): CanonicalTypeLeft&CanonicalTypeRight
    {
        throw new \LogicException();
    }
}
