<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ResetOnClone;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResetOnClone::class)]
class ResetOnCloneTest extends TestCase
{
    public function testParse(): void
    {
        static::assertSame(['reset_on_clone' => true], iterator_to_array((new ResetOnClone())->parse()));
    }
}
