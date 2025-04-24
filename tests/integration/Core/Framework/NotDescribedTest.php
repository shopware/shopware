<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class NotDescribedTest extends TestCase
{
    public function testNothing(): void
    {
        $this->markTestSkipped('');
    }
}
