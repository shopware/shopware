<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\DataAbstractionLayer\ShippingMethodIndexer;
use Shopware\Core\Checkout\Shipping\DataAbstractionLayer\ShippingMethodIndexingMessage;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ShippingMethodIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testIterate(): void
    {
        /** @var EntityWriter $entityWriter */
        $entityWriter = $this->getContainer()->get(EntityWriter::class);
        /** @var EntityDefinition $definition */
        $definition = $this->getContainer()->get(ShippingMethodDefinition::class);
        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());

        $newPriceId = Uuid::randomHex();
        $oldPriceId = Uuid::randomHex();

        $shippingMethodNew = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'active' => true,
            'availabilityRuleId' => $this->getAvailableShippingMethod()->getAvailabilityRuleId(),
            'deliveryTime' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
                'min' => 1,
                'max' => 3,
                'unit' => 'days',
            ],
            'prices' => [
                [
                    'id' => $newPriceId,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 12.37,
                            'gross' => 13.37,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
        ];
        $shippingMethodOld = $shippingMethodNew;
        $shippingMethodOld['id'] = Uuid::randomHex();
        $shippingMethodOld['prices'][0] = [
            'id' => $oldPriceId,
            'currencyPrice' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'net' => 12.37,
                    'gross' => 13.37,
                    'linked' => false,
                ],
            ],
        ];

        $entityWriter->upsert($definition, [$shippingMethodNew, $shippingMethodOld], $writeContext);

        /** @var ShippingMethodIndexer $indexer */
        $indexer = $this->getContainer()->get(ShippingMethodIndexer::class);
        $message = $indexer->iterate(['offset' => 0]);

        static::assertInstanceOf(ShippingMethodIndexingMessage::class, $message);

        $data = $message->getData();
        static::assertContains($shippingMethodOld['id'], $data);
        static::assertContains($shippingMethodNew['id'], $data);

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('shipping_method_price.repository');

        $indexer->handle($message);

        /** @var ShippingMethodPriceEntity $actualNew */
        $actualNew = $repository->search(new Criteria([$newPriceId]), Context::createDefaultContext())->first();
        static::assertNotNull($actualNew);
        $actualNew = $actualNew->jsonSerialize();

        static::assertNotNull($actualNew['currencyPrice']);

        $actualOld = $repository->search(new Criteria([$oldPriceId]), Context::createDefaultContext())->first();
        static::assertNotNull($actualOld);
        $actualOld = $actualOld->jsonSerialize();

        static::assertNotNull($actualOld['currencyPrice']);
    }
}
