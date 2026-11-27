<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Projection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Projected;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Projected::class)]
class ProjectedTest extends TestCase
{
    public function testExposesProjectionAndExportsFlag(): void
    {
        $projection = static::createStub(Projection::class);
        $flag = new Projected($projection);

        static::assertSame($projection, $flag->projection);
        static::assertSame(['projected' => true], iterator_to_array($flag->parse()));
    }
}
