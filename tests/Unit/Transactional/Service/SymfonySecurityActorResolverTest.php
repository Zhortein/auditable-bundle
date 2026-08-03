<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit\Transactional\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Security\TestUser;
use Zhortein\AuditableBundle\Transactional\Exception\ActorResolutionException;
use Zhortein\AuditableBundle\Transactional\Service\SymfonySecurityActorResolver;

final class SymfonySecurityActorResolverTest extends TestCase
{
    public function testReturnsNullWhenTokenIsAbsent(): void
    {
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->expects(self::once())->method('getToken')->willReturn(null);
        self::assertNull((new SymfonySecurityActorResolver($storage))->resolveActor());
    }

    public function testReturnsNullWhenOrdinaryTokenHasNoUser(): void
    {
        $token = $this->token(null);
        self::assertNull($this->resolver($token)->resolveActor());
    }

    #[DataProvider('validIdentifiers')]
    public function testResolvesAuthenticatedUserExactly(string $identifier): void
    {
        $user = $this->user($identifier);
        $actor = $this->resolver($this->token($user))->resolveActor();
        self::assertNotNull($actor);
        self::assertSame('authenticated_user', $actor->type);
        self::assertSame($identifier, $actor->identifier);
        self::assertNull($actor->impersonatorIdentifier);
        self::assertSame([], $actor->metadata);
    }

    /** @return iterable<string, array{string}> */
    public static function validIdentifiers(): iterable
    {
        yield 'zero' => ['0'];
        yield 'UTF-8' => ['utilisateur-é'];
        yield 'significant spaces' => [' utilisateur '];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testRejectsInvalidCurrentIdentifier(string $identifier): void
    {
        $this->expectException(ActorResolutionException::class);
        $this->resolver($this->token($this->user($identifier)))->resolveActor();
    }

    /** @return iterable<string, array{string}> */
    public static function invalidIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [" \t\n"];
        yield 'invalid UTF-8' => ["\xC3\x28"];
    }

    public function testWrapsTokenStorageFailure(): void
    {
        $previous = new \LogicException('storage failure');
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->expects(self::once())->method('getToken')->willThrowException($previous);
        $this->assertWrappedPrevious(new SymfonySecurityActorResolver($storage), $previous);
    }

    public function testWrapsCurrentUserReadFailure(): void
    {
        $previous = new \LogicException('token failure');
        $token = $this->createMock(TokenInterface::class);
        $token->expects(self::once())->method('getUser')->willThrowException($previous);
        $this->assertWrappedPrevious($this->resolver($token), $previous);
    }

    public function testWrapsIdentifierReadFailureWithoutLeakingIt(): void
    {
        $previous = new \LogicException('sensitive-raw-value');
        $user = $this->createMock(UserInterface::class);
        $user->expects(self::once())->method('getUserIdentifier')->willThrowException($previous);
        $user->expects(self::never())->method('getRoles');
        try {
            $this->resolver($this->token($user))->resolveActor();
            self::fail('Actor resolution should have failed.');
        } catch (ActorResolutionException $exception) {
            self::assertSame($previous, $exception->getPrevious());
            self::assertStringNotContainsString('sensitive-raw-value', $exception->getMessage());
        }
    }

    public function testResolvesImmediateSwitchUserContext(): void
    {
        $current = $this->user('effective-user');
        $original = $this->user('original-user');
        $originalToken = $this->token($original);
        $switchToken = new SwitchUserToken($current, 'test', [], $originalToken);
        $actor = $this->resolver($switchToken)->resolveActor();
        self::assertNotNull($actor);
        self::assertSame('effective-user', $actor->identifier);
        self::assertSame('original-user', $actor->impersonatorIdentifier);
        self::assertSame([], $actor->metadata);
    }

    public function testUsesOnlyImmediateOriginalTokenWhenSwitchTokensAreNested(): void
    {
        $rootToken = new UsernamePasswordToken(new TestUser('root-user'), 'test');
        $inner = new SwitchUserToken(new TestUser('immediate-user'), 'test', [], $rootToken);
        $outer = new SwitchUserToken(new TestUser('effective-user'), 'test', [], $inner);
        $actor = $this->resolver($outer)->resolveActor();
        self::assertNotNull($actor);
        self::assertSame('immediate-user', $actor->impersonatorIdentifier);
    }

    public function testRejectsMissingOriginalUserWithoutPartialResult(): void
    {
        $switchToken = new SwitchUserToken(new TestUser('effective-user'), 'test', [], $this->token(null));
        $this->expectException(ActorResolutionException::class);
        $this->resolver($switchToken)->resolveActor();
    }

    #[DataProvider('invalidIdentifiers')]
    public function testRejectsInvalidOriginalIdentifierWithoutLeakingIt(string $identifier): void
    {
        $switchToken = new SwitchUserToken(new TestUser('effective-user'), 'test', [], $this->token($this->user($identifier)));
        try {
            $this->resolver($switchToken)->resolveActor();
            self::fail('Actor resolution should have failed.');
        } catch (ActorResolutionException $exception) {
            if ('' !== $identifier) {
                self::assertStringNotContainsString($identifier, $exception->getMessage());
            }
            self::assertSame('The impersonator identifier is invalid.', $exception->getMessage());
        }
    }

    public function testWrapsOriginalUserReadFailure(): void
    {
        $previous = new \LogicException('original token failure');
        $originalToken = $this->createMock(TokenInterface::class);
        $originalToken->expects(self::once())->method('getUser')->willThrowException($previous);
        $switchToken = new SwitchUserToken(new TestUser('effective-user'), 'test', [], $originalToken);
        $this->assertWrappedPrevious($this->resolver($switchToken), $previous);
    }

    public function testNeverCallsBusinessIdentifiersOrRoles(): void
    {
        $actor = $this->resolver(new UsernamePasswordToken(new TestUser('security-identifier'), 'test'))->resolveActor();
        self::assertNotNull($actor);
        self::assertSame('security-identifier', $actor->identifier);
    }

    /** @return UserInterface&MockObject */
    private function user(string $identifier): UserInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->expects(self::once())->method('getUserIdentifier')->willReturn($identifier);
        $user->expects(self::never())->method('getRoles');

        return $user;
    }

    /** @return TokenInterface&MockObject */
    private function token(?UserInterface $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->expects(self::once())->method('getUser')->willReturn($user);

        return $token;
    }

    private function resolver(TokenInterface $token): SymfonySecurityActorResolver
    {
        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->expects(self::once())->method('getToken')->willReturn($token);

        return new SymfonySecurityActorResolver($storage);
    }

    private function assertWrappedPrevious(SymfonySecurityActorResolver $resolver, \Throwable $previous): void
    {
        try {
            $resolver->resolveActor();
            self::fail('Actor resolution should have failed.');
        } catch (ActorResolutionException $exception) {
            self::assertSame($previous, $exception->getPrevious());
        }
    }
}
