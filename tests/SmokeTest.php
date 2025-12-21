<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests;

use PHPUnit\Framework\TestCase;
use Zhortein\AuditableBundle\ZhorteinAuditableBundle;

final class SmokeTest extends TestCase
{
    public function testBundleClassIsLoadable(): void
    {
        self::assertTrue(class_exists(ZhorteinAuditableBundle::class));
    }
}
