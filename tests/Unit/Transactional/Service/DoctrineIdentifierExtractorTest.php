<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit\Transactional\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Transactional\Exception\IdentifierExtractionException;
use Zhortein\AuditableBundle\Transactional\Service\DoctrineIdentifierExtractor;

final class DoctrineIdentifierExtractorTest extends TestCase
{
    #[DataProvider('validSimpleIdentifiers')]
    public function testCanonicalizesSimpleIdentifier(mixed $value, string $expected): void
    {
        $entity = new \stdClass();
        $extractor = $this->extractor($entity, ['identifier'], ['identifier' => $value], 'Mapped\\Subject');

        $subject = $extractor->extract($entity);
        self::assertSame('Mapped\\Subject', $subject->type);
        self::assertSame($expected, $subject->identifier);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function validSimpleIdentifiers(): iterable
    {
        yield 'positive integer' => [42, '42'];
        yield 'zero' => [0, '0'];
        yield 'negative integer' => [-1, '-1'];
        yield 'string' => ['customer-42', 'customer-42'];
        yield 'spaces preserved' => [' customer-42 ', ' customer-42 '];
        yield 'string backed enum' => [StringIdentifier::ACTIVE, 'active'];
        yield 'integer backed enum' => [IntegerIdentifier::FORTY_TWO, '42'];
        yield 'Stringable UUID' => [new CountingStringable('0195f1dc-47ae-7ad2-b67f-9f5666dbbe37'), '0195f1dc-47ae-7ad2-b67f-9f5666dbbe37'];
    }

    #[DataProvider('invalidSimpleIdentifiers')]
    public function testRejectsUnsupportedOrInvalidSimpleIdentifier(mixed $value, string $type): void
    {
        $entity = new \stdClass();
        $extractor = $this->extractor($entity, ['identifier'], ['identifier' => $value]);

        try {
            $extractor->extract($entity);
            self::fail('Identifier extraction should have failed.');
        } catch (IdentifierExtractionException $exception) {
            self::assertStringContainsString($type, $exception->getMessage());
            self::assertStringNotContainsString('sensitive-raw-value', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidSimpleIdentifiers(): iterable
    {
        yield 'empty string' => ['', 'empty or blank'];
        yield 'blank string' => [" \t\n", 'empty or blank'];
        yield 'invalid UTF-8' => ["\xC3\x28", 'invalid UTF-8'];
        yield 'empty Stringable' => [new CountingStringable(''), 'empty or blank'];
        yield 'blank Stringable' => [new CountingStringable('  '), 'empty or blank'];
        yield 'boolean' => [true, 'bool'];
        yield 'float' => [1.5, 'float'];
        yield 'array' => [['sensitive-raw-value'], 'array'];
        yield 'object' => [new \stdClass(), 'stdClass'];
    }

    public function testStringableIsCalledExactlyOnce(): void
    {
        $entity = new \stdClass();
        $value = new CountingStringable('uuid');
        $subject = $this->extractor($entity, ['id'], ['id' => $value])->extract($entity);
        self::assertSame('uuid', $subject->identifier);
        self::assertSame(1, $value->calls);
    }

    public function testWrapsStringableFailureWithoutLeakingValue(): void
    {
        $entity = new \stdClass();
        $previous = new \LogicException('conversion failed');
        $extractor = $this->extractor($entity, ['id'], ['id' => new ThrowingStringable($previous)]);
        try {
            $extractor->extract($entity);
            self::fail('Identifier extraction should have failed.');
        } catch (IdentifierExtractionException $exception) {
            self::assertSame($previous, $exception->getPrevious());
            self::assertStringNotContainsString('sensitive-raw-value', $exception->getMessage());
        }
    }

    public function testRejectsResource(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);
        try {
            $entity = new \stdClass();
            $this->expectException(IdentifierExtractionException::class);
            $this->expectExceptionMessage('resource');
            $this->extractor($entity, ['id'], ['id' => $resource])->extract($entity);
        } finally {
            fclose($resource);
        }
    }

    /**
     * @param list<string>         $fields
     * @param array<string, mixed> $values
     */
    #[DataProvider('unavailableIdentifiers')]
    public function testRejectsUnavailableIdentifier(array $fields, array $values, string $message): void
    {
        $entity = new \stdClass();
        $this->expectException(IdentifierExtractionException::class);
        $this->expectExceptionMessage($message);
        $this->extractor($entity, $fields, $values)->extract($entity);
    }

    /** @return iterable<string, array{list<string>, array<string, mixed>, string}> */
    public static function unavailableIdentifiers(): iterable
    {
        yield 'no fields' => [[], [], 'declares no identifier field'];
        yield 'missing simple value' => [['id'], [], 'not available'];
        yield 'null omitted value' => [['id'], ['id' => null], 'not available'];
        yield 'missing composite field' => [['country', 'number'], ['country' => 'FR'], '"number" is not available'];
    }

    public function testCanonicalizesCompositeIdentifierInLexicalOrderWithTypesAndUnescapedCharacters(): void
    {
        $entity = new \stdClass();
        $subject = $this->extractor($entity, ['number', 'path', 'country'], [
            'number' => 42,
            'path' => 'Île/Paris',
            'country' => 'FR',
        ])->extract($entity);

        self::assertSame('{"format":"doctrine-composite-v1","fields":{"country":{"type":"string","value":"FR"},"number":{"type":"integer","value":"42"},"path":{"type":"string","value":"Île/Paris"}}}', $subject->identifier);
    }

    public function testCompositeIntegerAndStringRepresentationsDiffer(): void
    {
        $integerEntity = new \stdClass();
        $stringEntity = new \stdClass();
        $integer = $this->extractor($integerEntity, ['country', 'number'], ['country' => 'FR', 'number' => 42])->extract($integerEntity);
        $string = $this->extractor($stringEntity, ['country', 'number'], ['country' => 'FR', 'number' => '42'])->extract($stringEntity);
        self::assertNotSame($integer->identifier, $string->identifier);
        self::assertSame('{"format":"doctrine-composite-v1","fields":{"country":{"type":"string","value":"FR"},"number":{"type":"integer","value":"42"}}}', $integer->identifier);
        self::assertSame('{"format":"doctrine-composite-v1","fields":{"country":{"type":"string","value":"FR"},"number":{"type":"string","value":"42"}}}', $string->identifier);
    }

    public function testRejectsAssociationIdentifier(): void
    {
        $entity = new \stdClass();
        $related = new \stdClass();
        $extractor = $this->extractor($entity, ['related'], ['related' => $related], associations: ['related']);
        $this->expectException(IdentifierExtractionException::class);
        $this->expectExceptionMessage('custom extractor or an explicit AuditSubject');
        $extractor->extract($entity);
    }

    public function testRejectsMissingOrNonOrmManager(): void
    {
        $entity = new \stdClass();
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagerForClass')->with($entity::class)->willReturn(null);
        $this->expectException(IdentifierExtractionException::class);
        (new DoctrineIdentifierExtractor($registry))->extract($entity);
    }

    public function testRejectsNonOrmManager(): void
    {
        $entity = new \stdClass();
        $manager = $this->createMock(ObjectManager::class);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagerForClass')->willReturn($manager);
        $this->expectException(IdentifierExtractionException::class);
        $this->expectExceptionMessage('not a Doctrine ORM entity manager');
        (new DoctrineIdentifierExtractor($registry))->extract($entity);
    }

    public function testWrapsRegistryFailure(): void
    {
        $entity = new \stdClass();
        $previous = new \LogicException('registry failure');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagerForClass')->willThrowException($previous);
        try {
            (new DoctrineIdentifierExtractor($registry))->extract($entity);
            self::fail('Identifier extraction should have failed.');
        } catch (IdentifierExtractionException $exception) {
            self::assertSame($previous, $exception->getPrevious());
        }
    }

    public function testWrapsMetadataFailure(): void
    {
        $entity = new \stdClass();
        $previous = new \LogicException('metadata failure');
        [$registry, $manager] = $this->manager($entity);
        $manager->expects(self::once())->method('getClassMetadata')->willThrowException($previous);
        try {
            (new DoctrineIdentifierExtractor($registry))->extract($entity);
            self::fail('Identifier extraction should have failed.');
        } catch (IdentifierExtractionException $exception) {
            self::assertSame($previous, $exception->getPrevious());
        }
    }

    /**
     * @param list<string>         $fields
     * @param array<string, mixed> $values
     * @param list<string>         $associations
     */
    private function extractor(object $entity, array $fields, array $values, string $mappedClass = 'Mapped\\Entity', array $associations = []): DoctrineIdentifierExtractor
    {
        [$registry, $manager] = $this->manager($entity);
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects(self::atLeastOnce())->method('getName')->willReturn($mappedClass);
        $metadata->expects(self::once())->method('getIdentifierFieldNames')->willReturn($fields);
        $metadata->expects([] === $fields ? self::never() : self::once())->method('getIdentifierValues')->with($entity)->willReturn($values);
        $metadata->method('hasAssociation')->willReturnCallback(static fn (string $field): bool => \in_array($field, $associations, true));
        $manager->expects(self::once())->method('getClassMetadata')->with($entity::class)->willReturn($metadata);

        return new DoctrineIdentifierExtractor($registry);
    }

    /** @return array{ManagerRegistry&MockObject, EntityManagerInterface&MockObject} */
    private function manager(object $entity): array
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        foreach (['persist', 'flush', 'find', 'getRepository', 'getConnection', 'getUnitOfWork'] as $method) {
            $manager->expects(self::never())->method($method);
        }
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagerForClass')->with($entity::class)->willReturn($manager);

        return [$registry, $manager];
    }
}

enum StringIdentifier: string
{
    case ACTIVE = 'active';
}

enum IntegerIdentifier: int
{
    case FORTY_TWO = 42;
}

final class CountingStringable implements \Stringable
{
    public int $calls = 0;

    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        ++$this->calls;

        return $this->value;
    }
}

final readonly class ThrowingStringable implements \Stringable
{
    public function __construct(private \Throwable $exception)
    {
    }

    public function __toString(): string
    {
        throw $this->exception;
    }
}
