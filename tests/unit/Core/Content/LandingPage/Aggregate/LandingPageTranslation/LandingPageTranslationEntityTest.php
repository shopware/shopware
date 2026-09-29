<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\LandingPage\Aggregate\LandingPageTranslation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageTranslationEntity::class)]
class LandingPageTranslationEntityTest extends TestCase
{
    public function testSlotConfigCanBeSet(): void
    {
        $landingPage = new LandingPageTranslationEntity();
        $landingPage->setSlotConfig(['slot-id' => ['content' => ['source' => 'static']]]);

        static::assertSame(['slot-id' => ['content' => ['source' => 'static']]], $landingPage->getSlotConfig());
    }

    public function testANullSlotConfigIsRejected(): void
    {
        $landingPage = new LandingPageTranslationEntity();

        $this->expectException(\Throwable::class);

        $landingPage->setSlotConfig(null);
    }
}
