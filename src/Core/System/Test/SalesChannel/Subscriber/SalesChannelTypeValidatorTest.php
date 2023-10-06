<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Exception\DefaultSalesChannelTypeCannotBeDeleted;

/**
 * @internal
 */
#[Package('sales-channel')]
class SalesChannelTypeValidatorTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @dataProvider listAvailable
     */
    public function testCannotBeDeleted(string $id): void
    {
        $repo = $this->getContainer()->get('sales_channel_type.repository');

        try {
            $repo->delete([
                [
                    'id' => $id,
                ],
            ], Context::createDefaultContext());
        } catch (WriteException $e) {
            static::assertInstanceOf(DefaultSalesChannelTypeCannotBeDeleted::class, $e->getExceptions()[0]);

            return;
        }

        static::fail('Exception DefaultSalesChannelTypeCannotBeDeleted did not fired');
    }

    public function testDeleteOtherItem(): void
    {
        $repo = $this->getContainer()->get('sales_channel_type.repository');
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $repo->create([
            [
                'id' => $id,
                'name' => 'test',
            ],
        ], $context);

        $repo->delete([
            [
                'id' => $id,
            ],
        ], $context);

        static::assertNull($repo->searchIds(new Criteria([$id]), $context)->firstId());
    }

    public function testDeleteSalesChannel(): void
    {
        $id = $this->createSalesChannel()['id'];

        $repo = $this->getContainer()->get('sales_channel.repository');
        $repo->delete([
            [
                'id' => $id,
            ],
        ], Context::createDefaultContext());
    }

    public static function listAvailable(): array
    {
        return [
            [Defaults::SALES_CHANNEL_TYPE_API],
            [Defaults::SALES_CHANNEL_TYPE_STOREFRONT],
            [Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON],
        ];
    }
}
