<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\LandingPage\LandingPage;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(LandingPage::class)]
class LandingPageTest extends TestCase
{
    public function testLandingPage(): void
    {
        $page = new LandingPage();
        $entity = new LandingPageEntity();

        $page->setLandingPage($entity);

        static::assertSame(LandingPageDefinition::ENTITY_NAME, $page->getEntityName());
        static::assertSame($entity, $page->getLandingPage());
    }
}
