<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupCountPackager;
use Shopware\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceAscSorter;
use Shopware\Core\Checkout\Promotion\Api\PromotionActionController;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Promotion\Api\PromotionActionController
 */
#[Package('buyers-experience')]
class PromotionActionControllerTest extends TestCase
{
    private PromotionActionController $promotionActionController;

    protected function setUp(): void
    {
        $packager = $this->createMock(LineItemGroupCountPackager::class);
        $packager->method('getKey')->willReturn('test-packager');

        $sorter = $this->createMock(LineItemGroupPriceAscSorter::class);
        $sorter->method('getKey')->willReturn('test-sorter');

        $serviceRegistry = new LineItemGroupServiceRegistry(
            [$packager],
            [$sorter],
        );

        $this->promotionActionController = new PromotionActionController(
            $serviceRegistry
        );
    }

    public function testSetGroupPackager(): void
    {
        $response = $this->promotionActionController->getSetGroupPackagers();

        $content = $response->getContent();
        static::assertNotFalse($content);

        $json = \json_decode($content, null, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($json);

        static::assertCount(1, $json);
        static::assertContains('test-packager', $json);
    }

    public function testSetGroupSorters(): void
    {
        $response = $this->promotionActionController->getSetGroupSorters();

        $content = $response->getContent();
        static::assertNotFalse($content);

        $json = \json_decode($content, null, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($json);

        static::assertCount(1, $json);
        static::assertContains('test-sorter', $json);
    }
}
