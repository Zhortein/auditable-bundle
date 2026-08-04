<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit\Transactional\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Transactional\Model\AuditSubject;

final class AuditSubjectTest extends TestCase
{
    #[DataProvider('validSubjects')]
    public function testPreservesValidSubject(string $type, string $identifier): void
    {
        $subject = new AuditSubject($type, $identifier);
        self::assertSame($type, $subject->type);
        self::assertSame($identifier, $subject->identifier);
    }

    /** @return iterable<string, array{string, string}> */
    public static function validSubjects(): iterable
    {
        yield 'nominal' => ['App\\Entity\\Order', '42'];
        yield 'uuid' => ['order', '4f0af080-3771-46c4-9ec2-7ff09e1f4dd2'];
        yield 'integer string' => ['order', '123'];
        yield 'canonical composite JSON' => ['order_item', '{"order":42,"line":3}'];
        yield 'zero' => ['0', '0'];
        yield 'no normalization' => [' order ', ' identifier '];
    }

    #[DataProvider('invalidSubjects')]
    public function testRejectsEmptyParts(string $type, string $identifier): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuditSubject($type, $identifier);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidSubjects(): iterable
    {
        yield 'empty type' => ['', 'id'];
        yield 'blank type' => [" \t\n", 'id'];
        yield 'empty identifier' => ['type', ''];
        yield 'blank identifier' => ['type', " \t\n"];
    }

    public function testIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(AuditSubject::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }
}
