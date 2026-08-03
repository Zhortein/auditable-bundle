<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration\Transactional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Zhortein\AuditableBundle\Tests\Fixtures\App\TestKernel;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditEntryFactory;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\CapturingAuditStorage;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Recorder\FrozenClock;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Security\TestUser;
use Zhortein\AuditableBundle\Transactional\Contract\AuditActorResolverInterface;
use Zhortein\AuditableBundle\Transactional\Contract\IdentifierExtractorInterface;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;
use Zhortein\AuditableBundle\Transactional\Model\AuditEvent;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;
use Zhortein\AuditableBundle\Transactional\Service\StrictAuditRecorder;
use Zhortein\AuditableBundle\Transactional\Service\SymfonySecurityActorResolver;

final class StrictAuditRecorderIntegrationTest extends TestCase
{
    private TestKernel $kernel;
    private TokenStorageInterface $tokenStorage;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $tokenStorage = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $this->tokenStorage = $tokenStorage;
    }

    protected function tearDown(): void
    {
        $cacheDir = $this->kernel->getCacheDir();
        $this->kernel->shutdown();
        restore_exception_handler();
        self::removeDirectory($cacheDir);
    }

    public function testAuthenticatedUserWithExplicitSubjectIsCaptured(): void
    {
        $time = new \DateTimeImmutable('2026-08-03 09:10:11.123456+02:00');
        $subject = new AuditSubject('order', '42');
        $factory = new CapturingAuditEntryFactory();
        $storage = new CapturingAuditStorage();
        $recorder = new StrictAuditRecorder(
            $this->neverExtractor(),
            new SymfonySecurityActorResolver($this->tokenStorage),
            new FrozenClock($time),
            $factory,
            $storage,
        );
        $this->tokenStorage->setToken(new UsernamePasswordToken(new TestUser('authenticated-user'), 'test'));
        try {
            $recorder->record(new AuditEvent('update', 'Updated', subject: $subject, data: ['changed' => true]));
            self::assertCount(1, $storage->entries);
            self::assertSame($factory->entries[0], $storage->entries[0]);
            $record = $storage->entries[0]->record;
            self::assertSame($subject, $record->subject);
            self::assertSame($time, $record->occurredAt);
            self::assertSame(['changed' => true], $record->data);
            self::assertNotNull($record->actor);
            self::assertSame('authenticated_user', $record->actor->type);
            self::assertSame('authenticated-user', $record->actor->identifier);
            self::assertSame([], $record->actor->metadata);
        } finally {
            $this->tokenStorage->setToken(null);
        }
    }

    public function testExplicitActorBypassesResolverWithoutToken(): void
    {
        $actor = new AuditActor('system', 'worker', metadata: ['source' => 'command']);
        $resolver = $this->createMock(AuditActorResolverInterface::class);
        $resolver->expects(self::never())->method('resolveActor');
        $factory = new CapturingAuditEntryFactory();
        $storage = new CapturingAuditStorage();
        $this->tokenStorage->setToken(null);
        try {
            (new StrictAuditRecorder($this->neverExtractor(), $resolver, new FrozenClock(new \DateTimeImmutable()), $factory, $storage))
                ->record(new AuditEvent('run', 'Command', actor: $actor));
            self::assertSame($actor, $storage->entries[0]->record->actor);
            self::assertSame(['source' => 'command'], $storage->entries[0]->record->actor->metadata);
        } finally {
            $this->tokenStorage->setToken(null);
        }
    }

    public function testAnonymousGlobalEventIsCaptured(): void
    {
        $time = new \DateTimeImmutable('2026-08-03T00:00:00Z');
        $factory = new CapturingAuditEntryFactory();
        $storage = new CapturingAuditStorage();
        $this->tokenStorage->setToken(null);
        try {
            (new StrictAuditRecorder(
                $this->neverExtractor(),
                new SymfonySecurityActorResolver($this->tokenStorage),
                new FrozenClock($time),
                $factory,
                $storage,
            ))->record(new AuditEvent('maintenance', 'Global', data: ['scope' => 'all']));
            $record = $storage->entries[0]->record;
            self::assertNull($record->subject);
            self::assertNull($record->actor);
            self::assertSame($time, $record->occurredAt);
            self::assertSame(['scope' => 'all'], $record->data);
        } finally {
            $this->tokenStorage->setToken(null);
        }
    }

    private function neverExtractor(): IdentifierExtractorInterface
    {
        $extractor = $this->createMock(IdentifierExtractorInterface::class);
        $extractor->expects(self::never())->method('extract');

        return $extractor;
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
