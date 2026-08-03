<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type PublicContract from PublicApiContractTest
 * @phpstan-import-type PublicApi from PublicApiContractTest
 */
final class PublicApi20ContractTest extends TestCase
{
    private const VERSION_ONE_SNAPSHOT = __DIR__.'/public-api-1.0.0.json';
    private const VERSION_TWO_SNAPSHOT = __DIR__.'/public-api-2.0.0.json';

    public function testPublicApiPreservesVersionTwoSnapshot(): void
    {
        self::assertVersionTwoContractIsPreserved(self::snapshot(self::VERSION_TWO_SNAPSHOT), PublicApiContractTest::publicApi());
    }

    public function testVersionTwoSnapshotContainsTheUnchangedVersionOneContract(): void
    {
        $versionOne = self::snapshot(self::VERSION_ONE_SNAPSHOT);
        $versionTwo = self::snapshot(self::VERSION_TWO_SNAPSHOT);

        foreach ($versionOne as $type => $contract) {
            self::assertArrayHasKey($type, $versionTwo, \sprintf('The 1.0 public type %s is missing from the 2.0 snapshot.', $type));
            self::assertSame($contract, $versionTwo[$type], \sprintf('The historical contract of %s changed in the 2.0 snapshot.', $type));
        }
    }

    public function testFuturePublicTypesRemainAllowed(): void
    {
        $snapshot = ['Existing\\PublicType' => self::contractFixture()];
        $current = $snapshot;
        $current['Future\\PublicType'] = self::contractFixture();

        self::assertVersionTwoContractIsPreserved($snapshot, $current);
    }

    /**
     * @param PublicApi $snapshot
     * @param PublicApi $current
     */
    #[DataProvider('incompatibleVersionTwoContracts')]
    public function testVersionTwoContractChangesAreRejected(array $snapshot, array $current): void
    {
        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        self::assertVersionTwoContractIsPreserved($snapshot, $current);
    }

    /** @return iterable<string, array{PublicApi, PublicApi}> */
    public static function incompatibleVersionTwoContracts(): iterable
    {
        $snapshot = ['Frozen\\PublicType' => self::contractFixture()];

        yield 'removed type' => [$snapshot, []];

        $changed = self::contractFixture();
        $changed['final'] = false;
        yield 'changed final state' => [$snapshot, ['Frozen\\PublicType' => $changed]];

        $changed = self::contractFixture();
        $changed['readonly'] = false;
        yield 'changed readonly state' => [$snapshot, ['Frozen\\PublicType' => $changed]];

        $changed = self::contractFixture();
        $changed['enum_cases'] = ['Changed' => 'changed'];
        yield 'changed enum cases' => [$snapshot, ['Frozen\\PublicType' => $changed]];

        $changed = self::contractFixture();
        $changed['public_properties']['value']['type'] = 'int';
        /** @var PublicApi $changedApi */
        $changedApi = ['Frozen\\PublicType' => $changed];
        yield 'changed public property' => [$snapshot, $changedApi];

        $changed = self::contractFixture();
        $changed['public_methods']['create']['return'] = 'int';
        /** @var PublicApi $changedApi */
        $changedApi = ['Frozen\\PublicType' => $changed];
        yield 'changed public method' => [$snapshot, $changedApi];

        $changed = self::contractFixture();
        $changed['public_methods']['__construct']['parameters'][0]['name'] = 'renamed';
        /** @var PublicApi $changedApi */
        $changedApi = ['Frozen\\PublicType' => $changed];
        yield 'changed constructor' => [$snapshot, $changedApi];

        $interface = self::contractFixture();
        $interface['kind'] = 'interface';
        $current = $interface;
        $current['public_methods']['additionalMethod'] = [
            'static' => false,
            'return' => 'void',
            'parameters' => [],
        ];
        yield 'added interface method' => [
            ['Frozen\\PublicType' => $interface],
            ['Frozen\\PublicType' => $current],
        ];
    }

    /**
     * @param PublicApi $snapshot
     * @param PublicApi $current
     */
    private static function assertVersionTwoContractIsPreserved(array $snapshot, array $current): void
    {
        PublicApiContractTest::assertHistoricalContractIsPreserved($snapshot, $current);

        foreach ($snapshot as $type => $contract) {
            if ('interface' === $contract['kind']) {
                self::assertSame(
                    $contract['public_methods'],
                    $current[$type]['public_methods'],
                    \sprintf('The 2.0 interface %s gained a method.', $type),
                );
            }
        }
    }

    /** @return PublicApi */
    private static function snapshot(string $file): array
    {
        self::assertFileExists($file);
        $snapshot = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($snapshot);
        /** @var PublicApi $typedSnapshot */
        $typedSnapshot = $snapshot;

        return $typedSnapshot;
    }

    /** @return PublicContract */
    private static function contractFixture(): array
    {
        return [
            'kind' => 'class',
            'final' => true,
            'readonly' => true,
            'attribute_targets' => null,
            'enum_cases' => ['Stable' => 'stable'],
            'public_properties' => [
                'value' => [
                    'type' => 'string',
                    'readonly' => true,
                    'static' => false,
                ],
            ],
            'public_methods' => [
                '__construct' => [
                    'static' => false,
                    'return' => null,
                    'parameters' => [[
                        'name' => 'value',
                        'type' => 'string',
                        'by_reference' => false,
                        'variadic' => false,
                        'has_default' => false,
                        'default' => null,
                    ]],
                ],
                'create' => [
                    'static' => true,
                    'return' => 'self',
                    'parameters' => [],
                ],
            ],
        ];
    }
}
