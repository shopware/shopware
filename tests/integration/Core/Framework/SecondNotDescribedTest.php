<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class SecondNotDescribedTest extends TestCase
{
    public function testNothing(): void
    {
        static::markTestSkipped('This is not a test, but a placeholder to ensure that the test suite fails Danger.');
    }
}
