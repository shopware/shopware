<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeCollection::class)]
class ThemeCollectionTest extends TestCase
{
    public function testGetByTechnicalName(): void
    {
        $storefront = self::theme('Storefront');

        $collection = new ThemeCollection([$storefront, self::theme('SwagTheme')]);

        static::assertSame($storefront, $collection->getByTechnicalName('Storefront'));
        static::assertNull($collection->getByTechnicalName('Unknown'));
    }

    private static function theme(string $technicalName): ThemeEntity
    {
        $theme = new ThemeEntity();
        $theme->setUniqueIdentifier($technicalName);
        $theme->setTechnicalName($technicalName);

        return $theme;
    }
}
