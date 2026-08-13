<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppEntity::class)]
class AppEntityTest extends TestCase
{
    #[IgnoreDeprecations]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedModuleAccessorsStayCallableOnAnUnpopulatedEntity(): void
    {
        $app = new AppEntity();

        static::assertSame([], $app->getModules());
        static::assertNull($app->getMainModule());
    }
}
