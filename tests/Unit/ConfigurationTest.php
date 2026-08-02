<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Zhortein\AuditableBundle\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testHistoricalDefaults(): void
    {
        self::assertSame([
            'enabled' => true,
            'async' => ['enabled' => true, 'transport' => 'async'],
            'listener' => ['track_insert' => true, 'track_update' => true, 'track_delete' => true],
            'fields' => ['max_string_length' => 180, 'global_ignored' => []],
        ], $this->process([]));
    }

    public function testExplicitHistoricalConfiguration(): void
    {
        $config = [
            'enabled' => false,
            'async' => ['enabled' => false, 'transport' => 'audit_transport'],
            'listener' => ['track_insert' => false, 'track_update' => false, 'track_delete' => false],
            'fields' => ['max_string_length' => 30, 'global_ignored' => ['updatedAt', 'version']],
        ];

        self::assertSame($config, $this->process($config));
    }

    /** @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
