<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration\Transactional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Tests\Fixtures\App\DoctrineTestFactory;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity\CompositeIdentifier;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity\GeneratedIdentifier;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity\IdMethodEntity;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity\PrivateStringIdentifier;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity\ProxyEntity;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity\ReverseCompositeIdentifier;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Entity\ThrowingGetterEntity;
use Zhortein\AuditableBundle\Transactional\Exception\IdentifierExtractionException;
use Zhortein\AuditableBundle\Transactional\Service\DoctrineIdentifierExtractor;

final class DoctrineIdentifierExtractorIntegrationTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineIdentifierExtractor $extractor;

    protected function setUp(): void
    {
        $this->entityManager = DoctrineTestFactory::createTransactionalEntityManager();
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        (new SchemaTool($this->entityManager))->createSchema($metadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(function (string $class): ?EntityManagerInterface {
            if (ProxyEntity::class === $class || is_subclass_of($class, ProxyEntity::class)) {
                return ProxyEntity::class === $class ? $this->entityManager : null;
            }

            if (!class_exists($class)) {
                return null;
            }

            /* @var class-string $class */
            return $this->entityManager->getMetadataFactory()->isTransient($class) ? null : $this->entityManager;
        });
        $this->extractor = new DoctrineIdentifierExtractor($registry);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->close();
    }

    public function testExtractsPrivateStringIdentifierWithoutGetter(): void
    {
        $subject = $this->extractor->extract(new PrivateStringIdentifier('private-42'));
        self::assertSame(PrivateStringIdentifier::class, $subject->type);
        self::assertSame('private-42', $subject->identifier);
    }

    public function testNeverCallsIdMethod(): void
    {
        $subject = $this->extractor->extract(new IdMethodEntity('method-42'));
        self::assertSame(IdMethodEntity::class, $subject->type);
        self::assertSame('method-42', $subject->identifier);
    }

    public function testNeverCallsUnusableGetId(): void
    {
        $subject = $this->extractor->extract(new ThrowingGetterEntity('getter-42'));
        self::assertSame(ThrowingGetterEntity::class, $subject->type);
        self::assertSame('getter-42', $subject->identifier);
    }

    public function testExtractsNewUnmanagedManualIdentifier(): void
    {
        $entity = new PrivateStringIdentifier('unmanaged-42');
        self::assertFalse($this->entityManager->contains($entity));
        self::assertSame('unmanaged-42', $this->extractor->extract($entity)->identifier);
        self::assertFalse($this->entityManager->contains($entity));
    }

    public function testRejectsNewGeneratedNullIdentifierWithoutManagingEntity(): void
    {
        $entity = new GeneratedIdentifier();
        self::assertFalse($this->entityManager->contains($entity));
        $this->expectException(IdentifierExtractionException::class);
        $this->expectExceptionMessage('not available');
        $this->extractor->extract($entity);
    }

    public function testCompositeIdentifierIsExactAndIndependentOfDeclarationOrder(): void
    {
        $first = $this->extractor->extract(new CompositeIdentifier(42, 'FR'));
        $second = $this->extractor->extract(new ReverseCompositeIdentifier('FR', 42));
        $expected = '{"format":"doctrine-composite-v1","fields":{"country":{"type":"string","value":"FR"},"number":{"type":"integer","value":"42"}}}';
        self::assertSame(CompositeIdentifier::class, $first->type);
        self::assertSame(ReverseCompositeIdentifier::class, $second->type);
        self::assertSame($expected, $first->identifier);
        self::assertSame($expected, $second->identifier);
    }

    public function testExtractsMappedTypeAndIdentifierFromDoctrineReference(): void
    {
        $entity = new ProxyEntity('proxy-42');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reference = $this->entityManager->getReference(ProxyEntity::class, 'proxy-42');
        self::assertInstanceOf(ProxyEntity::class, $reference);
        $subject = $this->extractor->extract($reference);
        self::assertSame(ProxyEntity::class, $subject->type);
        self::assertSame('proxy-42', $subject->identifier);
        self::assertStringNotContainsString('Proxies', $subject->type);
    }
}
