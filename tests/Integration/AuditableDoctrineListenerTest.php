<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Attribute\Auditable;
use Zhortein\AuditableBundle\Attribute\AuditField;
use Zhortein\AuditableBundle\Attribute\AuditIgnore;
use Zhortein\AuditableBundle\Metadata\AuditableMetadataProvider;

final class AuditableDoctrineListenerTest extends TestCase
{
    public function testAuditableEntityMetadata(): void
    {
        $metadataProvider = new AuditableMetadataProvider();
        $entity = new AuditableTestEntity();

        $metadata = $metadataProvider->getFor($entity);

        self::assertNotNull($metadata);
        self::assertSame('AuditableTest', $metadata->label);
        self::assertSame('test-context', $metadata->context);
        self::assertArrayHasKey('name', $metadata->fieldLabels);
        self::assertSame('Full Name', $metadata->fieldLabels['name']);
        self::assertContains('secret', $metadata->ignoredFields);
    }

    public function testNonAuditableEntityMetadata(): void
    {
        $metadataProvider = new AuditableMetadataProvider();
        $entity = new NonAuditableTestEntity();

        $metadata = $metadataProvider->getFor($entity);

        self::assertNull($metadata);
    }

    public function testDefaultLabelEmptyFieldLabelAndCache(): void
    {
        $metadataProvider = new AuditableMetadataProvider();
        $entity = new DefaultMetadataEntity();

        $first = $metadataProvider->getFor($entity);
        $second = $metadataProvider->getFor($entity);

        self::assertNotNull($first);
        self::assertSame('DefaultMetadataEntity', $first->label);
        self::assertNull($first->context);
        self::assertSame([], $first->fieldLabels);
        self::assertSame([], $first->ignoredFields);
        self::assertSame($first, $second);
    }

    public function testNonAuditableResultIsCached(): void
    {
        $provider = new AuditableMetadataProvider();
        $entity = new NonAuditableTestEntity();

        self::assertNull($provider->getFor($entity));
        self::assertNull($provider->getFor($entity));
        $cache = new \ReflectionProperty($provider, 'cache');
        $cacheValue = $cache->getValue($provider);
        self::assertIsArray($cacheValue);
        self::assertArrayHasKey(NonAuditableTestEntity::class, $cacheValue);
    }
}

#[Auditable(label: 'AuditableTest', context: 'test-context')]
final class AuditableTestEntity
{
    #[AuditField(label: 'Full Name')]
    private string $name = '';

    #[AuditIgnore]
    private string $secret = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }
}

final class NonAuditableTestEntity
{
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

#[Auditable]
final class DefaultMetadataEntity
{
    #[AuditField(label: '   ')]
    private string $emptyLabel = '';

    public function getEmptyLabel(): string
    {
        return $this->emptyLabel;
    }
}
