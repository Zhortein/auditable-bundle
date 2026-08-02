<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Contract;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Transactional\Contract\IdentifierExtractorInterface;
use Zhortein\AuditableBundle\Transactional\Exception\IdentifierExtractionException;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;
use Zhortein\AuditableBundle\Transactional\Service\DoctrineIdentifierExtractor;

final class DoctrineIdentifierExtractorContractTest extends TestCase
{
    public function testExtractorContractIsExact(): void
    {
        $reflection = new \ReflectionClass(DoctrineIdentifierExtractor::class);
        self::assertSame('Zhortein\\AuditableBundle\\Transactional\\Service', $reflection->getNamespaceName());
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->implementsInterface(IdentifierExtractorInterface::class));

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
        $parameters = $constructor->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('registry', $parameters[0]->getName());
        self::assertSame(ManagerRegistry::class, (string) $parameters[0]->getType());
        self::assertTrue($parameters[0]->isPromoted());
        self::assertTrue($reflection->getProperty('registry')->isPrivate());

        $publicMethods = array_values(array_filter(
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            static fn (\ReflectionMethod $method): bool => DoctrineIdentifierExtractor::class === $method->getDeclaringClass()->getName(),
        ));
        self::assertSame(['__construct', 'extract'], array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $publicMethods));
        $extract = $reflection->getMethod('extract');
        self::assertSame('object', (string) $extract->getParameters()[0]->getType());
        self::assertSame(AuditSubject::class, (string) $extract->getReturnType());
    }

    public function testExceptionContractIsExact(): void
    {
        $reflection = new \ReflectionClass(IdentifierExtractionException::class);
        self::assertSame('Zhortein\\AuditableBundle\\Transactional\\Exception', $reflection->getNamespaceName());
        self::assertTrue($reflection->isFinal());
        $parent = $reflection->getParentClass();
        self::assertInstanceOf(\ReflectionClass::class, $parent);
        self::assertSame(\RuntimeException::class, $parent->getName());
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            static fn (\ReflectionMethod $method): bool => IdentifierExtractionException::class === $method->getDeclaringClass()->getName(),
        )));
    }
}
