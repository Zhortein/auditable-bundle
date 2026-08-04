<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit\Transactional\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;

final class AuditActorTest extends TestCase
{
    public function testPreservesAuthenticatedActorImpersonationAndMetadata(): void
    {
        $metadata = ['tenant' => 7, 'roles' => ['ROLE_USER']];
        $actor = new AuditActor('user', '42', '1', $metadata);
        self::assertSame('user', $actor->type);
        self::assertSame('42', $actor->identifier);
        self::assertSame('1', $actor->impersonatorIdentifier);
        self::assertSame($metadata, $actor->metadata);
    }

    public function testAllowsSystemActorWithoutIdentifier(): void
    {
        $actor = new AuditActor('system');
        self::assertNull($actor->identifier);
        self::assertNull($actor->impersonatorIdentifier);
        self::assertSame([], $actor->metadata);
    }

    #[DataProvider('invalidActors')]
    public function testRejectsInvalidActor(string $type, ?string $identifier, ?string $impersonator): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuditActor($type, $identifier, $impersonator);
    }

    /** @return iterable<string, array{string, string|null, string|null}> */
    public static function invalidActors(): iterable
    {
        yield 'empty type' => ['', null, null];
        yield 'blank type' => ['  ', null, null];
        yield 'empty identifier' => ['user', '', null];
        yield 'blank identifier' => ['user', '  ', null];
        yield 'empty impersonator' => ['user', null, ''];
        yield 'blank impersonator' => ['user', null, '  '];
    }

    public function testIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(AuditActor::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }
}
