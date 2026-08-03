<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration\Transactional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TestKernel;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Security\TestUser;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Service\SymfonySecurityActorResolver;

final class SymfonySecurityActorResolverIntegrationTest extends TestCase
{
    private TestKernel $kernel;
    private TokenStorageInterface $tokenStorage;
    private SymfonySecurityActorResolver $resolver;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $tokenStorage = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $this->tokenStorage = $tokenStorage;
        $this->resolver = new SymfonySecurityActorResolver($tokenStorage);
    }

    protected function tearDown(): void
    {
        $cacheDir = $this->kernel->getCacheDir();
        $this->kernel->shutdown();
        restore_exception_handler();
        self::removeDirectory($cacheDir);
    }

    public function testNoToken(): void
    {
        $this->assertActorWithToken(null, static function (?object $actor): void {
            self::assertNull($actor);
        });
    }

    public function testAuthenticatedUser(): void
    {
        $token = new UsernamePasswordToken(new TestUser('user-42'), 'test');
        $this->assertActorWithToken($token, static function (?object $actor): void {
            self::assertNotNull($actor);
            self::assertSame('authenticated_user', $actor->type);
            self::assertSame('user-42', $actor->identifier);
            self::assertNull($actor->impersonatorIdentifier);
            self::assertSame([], $actor->metadata);
        });
    }

    public function testImpersonation(): void
    {
        $original = new UsernamePasswordToken(new TestUser('original-user'), 'test');
        $token = new SwitchUserToken(new TestUser('effective-user'), 'test', [], $original);
        $this->assertActorWithToken($token, static function (?object $actor): void {
            self::assertNotNull($actor);
            self::assertSame('authenticated_user', $actor->type);
            self::assertSame('effective-user', $actor->identifier);
            self::assertSame('original-user', $actor->impersonatorIdentifier);
            self::assertSame([], $actor->metadata);
        });
    }

    public function testUnusableBusinessGettersAreNeverCalled(): void
    {
        $token = new UsernamePasswordToken(new TestUser('security-identifier'), 'test');
        $this->assertActorWithToken($token, static function (?object $actor): void {
            self::assertNotNull($actor);
            self::assertSame('security-identifier', $actor->identifier);
        });
    }

    /** @param callable(?AuditActor): void $assertion */
    private function assertActorWithToken(?TokenInterface $token, callable $assertion): void
    {
        $this->tokenStorage->setToken($token);
        try {
            $assertion($this->resolver->resolveActor());
        } finally {
            $this->tokenStorage->setToken(null);
        }
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item instanceof \SplFileInfo && $item->isDir()) {
                rmdir($item->getPathname());
            } elseif ($item instanceof \SplFileInfo) {
                unlink($item->getPathname());
            }
        }
        rmdir($directory);
    }
}
