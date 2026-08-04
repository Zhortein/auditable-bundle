<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Service\ChangeDetector;

final class ChangeDetectorTest extends TestCase
{
    #[DataProvider('scalarAndObjectValues')]
    public function testCurrentlyProducedRepresentations(mixed $value, string $expected): void
    {
        self::assertSame($expected, self::stringify(new ChangeDetector(), $value));
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function scalarAndObjectValues(): iterable
    {
        yield 'null' => [null, '∅'];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'date time' => [new \DateTimeImmutable('2025-01-02 03:04:05+00:00'), '2025-01-02 03:04:05'];
        yield 'backed enum' => [DetectorEnum::VALUE, 'VALUE (stored)'];
        yield 'integer' => [42, '42'];
        yield 'float' => [12.5, '12.5'];
        yield 'string' => ['text', 'text'];
        yield 'serializable array' => [['url' => '/a/b', 'accent' => 'été'], '{"url":"/a/b","accent":"été"}'];
        yield 'stringable object' => [new DetectorStringable(), 'string value'];
        yield 'getName object' => [new DetectorNamed(), '[DetectorNamed#name value]'];
        yield 'getTitle object' => [new DetectorTitled(), '[DetectorTitled#title value]'];
        yield 'getId object' => [new DetectorIdentified(), '[DetectorIdentified#123]'];
        yield 'unknown object' => [new DetectorUnknown(), '[object DetectorUnknown]'];
    }

    public function testNonSerializableArray(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);
        self::assertSame('[array serialization error]', self::stringify(new ChangeDetector(), [$resource]));
        fclose($resource);
    }

    public function testEmptyCollection(): void
    {
        self::assertSame('[Collection 0 items: ]', self::stringify(new ChangeDetector(), new ArrayCollection()));
    }

    public function testCollectionSamplesKnownRepresentationsAndStopsAtThree(): void
    {
        $collection = new ArrayCollection([
            new DetectorStringable(),
            new DetectorNamed(),
            new DetectorTitled(),
            new DetectorIdentified(),
        ]);

        self::assertSame(
            '[Collection 4 items: string value, name value, title value, …]',
            self::stringify(new ChangeDetector(), $collection),
        );
    }

    public function testCollectionUsesIdAndUnknownClassName(): void
    {
        self::assertSame(
            '[Collection 2 items: DetectorIdentified#123, DetectorUnknown]',
            self::stringify(new ChangeDetector(), new ArrayCollection([new DetectorIdentified(), new DetectorUnknown()])),
        );
    }

    public function testTruncationKeepsConfiguredLengthAndAddsEllipsis(): void
    {
        self::assertSame('1234…', self::stringify(new ChangeDetector(5), '123456789'));
        self::assertSame(5, mb_strlen(self::stringify(new ChangeDetector(5), '123456789')));
    }

    public function testUtf8TruncationCountsCharactersAndPreservesMultibyteCharacters(): void
    {
        $result = self::stringify(new ChangeDetector(6), 'Éléphant à Tokyo');

        self::assertSame('Éléph…', $result);
        self::assertSame(6, mb_strlen($result));
        self::assertGreaterThan(6, \strlen($result));
        self::assertSame(1, preg_match('//u', $result));
    }

    public function testZeroAndNegativeLengthsDisableTruncation(): void
    {
        self::assertSame('123456789', self::stringify(new ChangeDetector(0), '123456789'));
        self::assertSame('123456789', self::stringify(new ChangeDetector(-1), '123456789'));
    }

    private static function stringify(ChangeDetector $detector, mixed $value): string
    {
        $method = new \ReflectionMethod($detector, 'stringify');
        $result = $method->invoke($detector, $value);
        self::assertIsString($result);

        return $result;
    }
}

enum DetectorEnum: string
{
    case VALUE = 'stored';
}

final class DetectorStringable
{
    public function __toString(): string
    {
        return 'string value';
    }
}

final class DetectorNamed
{
    public function getName(): string
    {
        return 'name value';
    }
}

final class DetectorTitled
{
    public function getTitle(): string
    {
        return 'title value';
    }
}

final class DetectorIdentified
{
    public function getId(): int
    {
        return 123;
    }
}

final class DetectorUnknown
{
}
