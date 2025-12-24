<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\Service\ChangeDetector;

final class ChangeDetectorTest extends TestCase
{
    private ChangeDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new ChangeDetector(maxStringLength: 180);
    }

    public function testDetectorCanBeInstantiated(): void
    {
        self::assertInstanceOf(ChangeDetector::class, $this->detector);
    }

    public function testDetectorWithCustomMaxStringLength(): void
    {
        $detector = new ChangeDetector(maxStringLength: 50);
        self::assertInstanceOf(ChangeDetector::class, $detector);
    }

    public function testDetectorWithZeroMaxStringLength(): void
    {
        $detector = new ChangeDetector(maxStringLength: 0);
        self::assertInstanceOf(ChangeDetector::class, $detector);
    }

    public function testDetectorWithNegativeMaxStringLength(): void
    {
        $detector = new ChangeDetector(maxStringLength: -1);
        self::assertInstanceOf(ChangeDetector::class, $detector);
    }

    public function testStringifyMethodExists(): void
    {
        $reflectionClass = new \ReflectionClass($this->detector);
        self::assertTrue($reflectionClass->hasMethod('stringify'));
    }

    public function testTruncateMethodExists(): void
    {
        $reflectionClass = new \ReflectionClass($this->detector);
        self::assertTrue($reflectionClass->hasMethod('truncate'));
    }

    public function testDetectorIsReadonly(): void
    {
        $reflectionClass = new \ReflectionClass($this->detector);
        self::assertTrue($reflectionClass->isReadonly());
    }
}
