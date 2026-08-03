<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Zhortein\AuditableBundle\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testHistoricalDefaults(): void
    {
        self::assertSame([
            'enabled' => true,
            'legacy_mapping' => ['enabled' => true],
            'transactional' => ['enabled' => false],
            'async' => ['enabled' => true, 'transport' => 'async'],
            'listener' => ['track_insert' => true, 'track_update' => true, 'track_delete' => true],
            'fields' => ['max_string_length' => 180, 'global_ignored' => []],
        ], $this->process([]));
    }

    public function testExplicitHistoricalConfiguration(): void
    {
        $config = [
            'enabled' => false,
            'legacy_mapping' => ['enabled' => true],
            'transactional' => ['enabled' => false],
            'async' => ['enabled' => false, 'transport' => 'audit_transport'],
            'listener' => ['track_insert' => false, 'track_update' => false, 'track_delete' => false],
            'fields' => ['max_string_length' => 30, 'global_ignored' => ['updatedAt', 'version']],
        ];

        self::assertSame($config, $this->process($config));
    }

    public function testTransactionalModeCanBeExplicitlyEnabled(): void
    {
        $config = $this->process(['transactional' => ['enabled' => true]]);

        self::assertSame(['enabled' => true], $config['transactional']);
        self::assertTrue($config['transactional']['enabled']);
    }

    public function testTransactionalModeCanBeExplicitlyDisabled(): void
    {
        $config = $this->process(['transactional' => ['enabled' => false]]);

        self::assertSame(['enabled' => false], $config['transactional']);
        self::assertFalse($config['transactional']['enabled']);
    }

    public function testTransactionalModeRejectsNonBooleanValues(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['transactional' => ['enabled' => 'yes']]);
    }

    public function testLegacyMappingCanBeExplicitlyEnabled(): void
    {
        $config = $this->process(['legacy_mapping' => ['enabled' => true]]);

        self::assertSame(['enabled' => true], $config['legacy_mapping']);
    }

    public function testLegacyMappingCanBeDisabledWithLegacyRuntime(): void
    {
        $config = $this->process([
            'enabled' => false,
            'legacy_mapping' => ['enabled' => false],
            'transactional' => ['enabled' => true],
        ]);

        self::assertSame(['enabled' => false], $config['legacy_mapping']);
        self::assertSame(['enabled' => true], $config['transactional']);
    }

    public function testLegacyMappingCannotBeDisabledWhileLegacyRuntimeIsEnabled(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The legacy Doctrine mapping cannot be disabled while legacy auditing is enabled. Set "enabled" to false first.');

        $this->process(['legacy_mapping' => ['enabled' => false]]);
    }

    public function testLegacyMappingRejectsNonBooleanValues(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['legacy_mapping' => ['enabled' => 'no']]);
    }

    /** @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
