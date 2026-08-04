<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditActorResolverInterface;
use Zhortein\AuditableBundle\Transactional\Exception\ActorResolutionException;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Service\SymfonySecurityActorResolver;

final class SymfonySecurityActorResolverContractTest extends TestCase
{
    public function testResolverContractIsExact(): void
    {
        self::assertTrue(class_exists(SymfonySecurityActorResolver::class));
        $reflection = new \ReflectionClass(SymfonySecurityActorResolver::class);
        self::assertSame('Zhortein\\AuditableBundle\\Transactional\\Service', $reflection->getNamespaceName());
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->implementsInterface(AuditActorResolverInterface::class));

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
        $parameters = $constructor->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('tokenStorage', $parameters[0]->getName());
        self::assertSame(TokenStorageInterface::class, (string) $parameters[0]->getType());
        self::assertTrue($parameters[0]->isPromoted());
        $properties = $reflection->getProperties();
        self::assertCount(1, $properties);
        self::assertSame('tokenStorage', $properties[0]->getName());
        self::assertTrue($properties[0]->isPrivate());

        $publicMethods = array_values(array_filter(
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            static fn (\ReflectionMethod $method): bool => SymfonySecurityActorResolver::class === $method->getDeclaringClass()->getName(),
        ));
        self::assertSame(['__construct', 'resolveActor'], array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $publicMethods));
        $resolve = $reflection->getMethod('resolveActor');
        self::assertCount(0, $resolve->getParameters());
        self::assertSame('?'.AuditActor::class, (string) $resolve->getReturnType());
    }

    public function testExceptionContractIsExact(): void
    {
        self::assertTrue(class_exists(ActorResolutionException::class));
        $reflection = new \ReflectionClass(ActorResolutionException::class);
        self::assertSame('Zhortein\\AuditableBundle\\Transactional\\Exception', $reflection->getNamespaceName());
        self::assertTrue($reflection->isFinal());
        $parent = $reflection->getParentClass();
        self::assertInstanceOf(\ReflectionClass::class, $parent);
        self::assertSame(\RuntimeException::class, $parent->getName());
        self::assertSame([], array_values(array_filter(
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            static fn (\ReflectionMethod $method): bool => ActorResolutionException::class === $method->getDeclaringClass()->getName(),
        )));
    }
}
