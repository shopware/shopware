<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductConfiguratorSetting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingExceptionHandler;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;

/**
 * @internal
 */
#[CoversClass(ProductConfiguratorSettingExceptionHandler::class)]
class ProductConfiguratorSettingExceptionHandlerTest extends TestCase
{
    public function testMatching(): void
    {
        $e = new \RuntimeException('An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry  for key \'product_configurator_setting.uniq.product_configurator_setting.prod_id.vers_id.prop_group_id\'');

        $handler = new ProductConfiguratorSettingExceptionHandler();

        static::assertInstanceOf(ProductException::class, $handler->matchException($e));
    }

    public function testNotMatching(): void
    {
        $e = new \RuntimeException('test');

        $handler = new ProductConfiguratorSettingExceptionHandler();

        static::assertNull($handler->matchException($e));
    }

    public function testPriority(): void
    {
        $handler = new ProductConfiguratorSettingExceptionHandler();

        static::assertSame(ExceptionHandlerInterface::PRIORITY_DEFAULT, $handler->getPriority());
    }
}
