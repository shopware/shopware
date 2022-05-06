<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\DocumentIdCollection;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

trait DocumentTrait
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    private function persistCart(Cart $cart): string
    {
        $cart = $this->getContainer()->get(CartService::class)->recalculate($cart, $this->salesChannelContext);
        $orderId = $this->getContainer()->get(OrderPersister::class)->persist($cart, $this->salesChannelContext);

        return $orderId;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getAvailablePaymentMethod()->getId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()->get('customer.repository')->upsert([$customer], $this->context);

        return $customerId;
    }

    /**
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     * @throws \Exception
     */
    private function generateDemoCart(int $lineItemCount): Cart
    {
        $cart = new Cart('A', 'a-b-c');

        $keywords = ['awesome', 'epic', 'high quality'];

        $products = [];

        $factory = new ProductLineItemFactory();

        for ($i = 0; $i < $lineItemCount; ++$i) {
            $id = Uuid::randomHex();

            $price = random_int(100, 200000) / 100.0;

            shuffle($keywords);
            $name = ucfirst(implode(' ', $keywords) . ' product');

            $products[] = [
                'id' => $id,
                'name' => $name,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price, 'linked' => false],
                ],
                'productNumber' => Uuid::randomHex(),
                'manufacturer' => ['id' => $id, 'name' => 'test'],
                'tax' => ['id' => $id, 'taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];

            $cart->add($factory->create($id));
            $this->addTaxDataToSalesChannel($this->salesChannelContext, end($products)['tax']);
        }

        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        $cart = $this->getContainer()->get(Processor::class)->process($cart, $this->salesChannelContext, new CartBehavior());

        return $cart;
    }

    private function getBaseConfig(string $documentType, ?string $salesChannelId = null): ?DocumentBaseConfigEntity
    {
        /** @var EntityRepositoryInterface $documentTypeRepository */
        $documentTypeRepository = $this->getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $documentType)),
            Context::createDefaultContext()
        )->firstId();

        /** @var EntityRepositoryInterface $documentBaseConfigRepository */
        $documentBaseConfigRepository = $this->getContainer()->get('document_base_config.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentTypeId', $documentTypeId));
        $criteria->addFilter(new EqualsFilter('global', true));

        if ($salesChannelId !== null) {
            $criteria->addFilter(new EqualsFilter('salesChannels.salesChannelId', $salesChannelId));
            $criteria->addFilter(new EqualsFilter('salesChannels.documentTypeId', $documentTypeId));
        }

        return $documentBaseConfigRepository->search($criteria, Context::createDefaultContext())->first();
    }

    private function createDocument(string $documentType, string $orderId, array $config, Context $context): DocumentIdCollection
    {
        $operations = [];
        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, $config);
        $operations[$orderId] = $operation;

        return $this->documentGenerator->generate($documentType, $operations, $context);
    }

    private function upsertBaseConfig($config, string $documentType, ?string $salesChannelId = null): void
    {
        $baseConfig = $this->getBaseConfig($documentType, $salesChannelId);

        /** @var EntityRepositoryInterface $documentTypeRepository */
        $documentTypeRepository = $this->getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $documentType)),
            Context::createDefaultContext()
        )->firstId();

        if ($baseConfig === null) {
            $documentConfigId = Uuid::randomHex();
        } else {
            $documentConfigId = $baseConfig->getId();
        }

        $data = [
            'id' => $documentConfigId,
            'typeId' => $documentTypeId,
            'documentTypeId' => $documentTypeId,
            'config' => $config,
        ];
        if ($baseConfig === null) {
            $data['name'] = $documentConfigId;
        }
        if ($salesChannelId !== null) {
            $data['salesChannels'] = [
                [
                    'documentBaseConfigId' => $documentConfigId,
                    'documentTypeId' => $documentTypeId,
                    'salesChannelId' => $salesChannelId,
                ],
            ];
        }

        /** @var EntityRepositoryInterface $documentBaseConfigRepository */
        $documentBaseConfigRepository = $this->getContainer()->get('document_base_config.repository');
        $documentBaseConfigRepository->upsert([$data], Context::createDefaultContext());
    }
}
