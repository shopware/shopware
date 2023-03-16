<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\ShopNameAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\ShopNameStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.6.0 - Will be removed
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer
 */
class ScalarFlowStorerTest extends TestCase
{
    public function testCallOldFirst(): void
    {
        $event = new BcEvent();

        $stored = [];
        $stored = (new ScalarValuesStorer())->store($event, $stored);

        // check old value is stored correctly
        if (!Feature::isActive('v6.6.0.0')) {
            $stored = (new ShopNameStorer())->store($event, $stored);
            static::assertArrayHasKey('shopName', $stored);
            static::assertEquals('my-shop-name', $stored['shopName']);
        }

        // check new value are stored correctly
        static::assertArrayHasKey(ScalarValuesAware::STORE_VALUES, $stored);
        static::assertArrayHasKey('shopName', $stored[ScalarValuesAware::STORE_VALUES]);
        static::assertArrayHasKey('whatWhenIChooseAnotherName', $stored[ScalarValuesAware::STORE_VALUES]);
        static::assertEquals('my-shop-name', $stored[ScalarValuesAware::STORE_VALUES]['shopName']);
        static::assertEquals('my-shop-name', $stored[ScalarValuesAware::STORE_VALUES]['whatWhenIChooseAnotherName']);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored, []);

        (new ScalarValuesStorer())->restore($flow);

        if (!Feature::isActive('v6.6.0.0')) {
            (new ShopNameStorer())->restore($flow);
        }

        static::assertEquals('my-shop-name', $flow->getData('shopName'));
        static::assertEquals('my-shop-name', $flow->getData('whatWhenIChooseAnotherName'));
    }

    public function testCallNewFirst(): void
    {
        $event = new BcEvent();

        $stored = [];

        if (!Feature::isActive('v6.6.0.0')) {
            $stored = (new ShopNameStorer())->store($event, $stored);
        }
        $stored = (new ScalarValuesStorer())->store($event, $stored);

        if (!Feature::isActive('v6.6.0.0')) {
            // check old value is stored correctly
            static::assertArrayHasKey('shopName', $stored);
            static::assertEquals('my-shop-name', $stored['shopName']);
        }

        // check new value are stored correctly
        static::assertArrayHasKey(ScalarValuesAware::STORE_VALUES, $stored);
        static::assertArrayHasKey('shopName', $stored[ScalarValuesAware::STORE_VALUES]);
        static::assertArrayHasKey('whatWhenIChooseAnotherName', $stored[ScalarValuesAware::STORE_VALUES]);
        static::assertEquals('my-shop-name', $stored[ScalarValuesAware::STORE_VALUES]['shopName']);
        static::assertEquals('my-shop-name', $stored[ScalarValuesAware::STORE_VALUES]['whatWhenIChooseAnotherName']);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored, []);

        if (!Feature::isActive('v6.6.0.0')) {
            (new ShopNameStorer())->restore($flow);
        }
        (new ScalarValuesStorer())->restore($flow);

        static::assertEquals('my-shop-name', $flow->getData('shopName'));
        static::assertEquals('my-shop-name', $flow->getData('whatWhenIChooseAnotherName'));
    }
}

/**
 * @deprecated tag:v6.6.0 - Will be removed
 *
 * @internal
 */
class BcEvent implements ShopNameAware, ScalarValuesAware
{
    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return 'foo';
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            'shopName' => $this->getShopName(),
            'whatWhenIChooseAnotherName' => $this->getShopName(),
        ];
    }

    public function getShopName(): string
    {
        return 'my-shop-name';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }
}
