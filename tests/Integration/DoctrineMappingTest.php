<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Entity\AuditEntry;
use Zhortein\AuditableBundle\Tests\Fixtures\App\DoctrineTestFactory;

final class DoctrineMappingTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = DoctrineTestFactory::createEntityManager();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->close();
    }

    public function testLegacyAuditEntryMappingIsExact(): void
    {
        $metadata = $this->entityManager->getClassMetadata(AuditEntry::class);

        self::assertSame(AuditEntry::class, $metadata->getName());
        self::assertSame('audit_entry', $metadata->getTableName());
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertTrue($metadata->isIdGeneratorIdentity());
        self::assertSame([], $metadata->associationMappings);
        $indexes = $metadata->table['indexes'] ?? null;
        self::assertIsArray($indexes);
        self::assertSame([
            'idx_audit_entry_occurred_at' => ['columns' => ['occurred_at']],
            'idx_audit_entry_entity' => ['columns' => ['entity_class', 'entity_id']],
        ], $indexes);

        $actual = [];
        foreach ($metadata->fieldMappings as $field => $mapping) {
            $actual[$mapping->columnName] = [
                'field' => $field,
                'type' => $mapping->type,
                'length' => $mapping->length,
                'nullable' => $mapping->nullable,
                'id' => $mapping->id ?? false,
                'options' => $mapping->options ?? [],
            ];
        }

        self::assertSame(self::expectedColumns(), $actual);
    }

    public function testGeneratedSchemaContainsOnlyLegacyAndFixtureTables(): void
    {
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $tables = $this->entityManager->getConnection()->createSchemaManager()->listTableNames();
        sort($tables);
        self::assertSame(['audit_entry', 'test_auditable_entity', 'test_non_auditable_entity'], $tables);
        self::assertCount(3, $tables);
        self::assertSame([], $this->entityManager->getClassMetadata(AuditEntry::class)->associationMappings);
    }

    /** @return array<string, array{field: string, type: string, length: int|null, nullable: bool, id: bool, options: array<string, mixed>}> */
    private static function expectedColumns(): array
    {
        return [
            'id' => ['field' => 'id', 'type' => 'integer', 'length' => null, 'nullable' => false, 'id' => true, 'options' => []],
            'occurred_at' => ['field' => 'occurredAt', 'type' => 'datetime_immutable', 'length' => null, 'nullable' => false, 'id' => false, 'options' => []],
            'action' => ['field' => 'action', 'type' => 'string', 'length' => 50, 'nullable' => false, 'id' => false, 'options' => []],
            'level' => ['field' => 'level', 'type' => 'string', 'length' => 20, 'nullable' => false, 'id' => false, 'options' => []],
            'title' => ['field' => 'title', 'type' => 'string', 'length' => 255, 'nullable' => false, 'id' => false, 'options' => []],
            'description' => ['field' => 'description', 'type' => 'text', 'length' => null, 'nullable' => true, 'id' => false, 'options' => []],
            'context' => ['field' => 'context', 'type' => 'string', 'length' => 255, 'nullable' => true, 'id' => false, 'options' => []],
            'entity_class' => ['field' => 'entityClass', 'type' => 'string', 'length' => 255, 'nullable' => true, 'id' => false, 'options' => []],
            'entity_id' => ['field' => 'entityId', 'type' => 'string', 'length' => 64, 'nullable' => true, 'id' => false, 'options' => []],
            'actor_id' => ['field' => 'actorId', 'type' => 'string', 'length' => 64, 'nullable' => true, 'id' => false, 'options' => []],
            'impersonator_id' => ['field' => 'impersonatorId', 'type' => 'string', 'length' => 64, 'nullable' => true, 'id' => false, 'options' => []],
            'is_auto' => ['field' => 'isAuto', 'type' => 'boolean', 'length' => null, 'nullable' => false, 'id' => false, 'options' => ['default' => false]],
            'data' => ['field' => 'data', 'type' => 'json', 'length' => null, 'nullable' => true, 'id' => false, 'options' => []],
        ];
    }
}
