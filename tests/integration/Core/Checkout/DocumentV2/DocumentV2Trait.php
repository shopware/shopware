<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
trait DocumentV2Trait
{
    use DocumentTrait;
    use PromotionTestFixtureBehaviour;

    protected const DOCUMENT_NUMBER = '1000';

    protected const DOCUMENT_DATE = '2026-05-05T12:00:00+00:00';

    protected Context $context;

    protected SalesChannelContext $salesChannelContext;

    /**
     * @return array<string, mixed>
     */
    protected function getDemoInvoiceLegacyConfig(): array
    {
        return [
            'documentNumber' => self::DOCUMENT_NUMBER,
            'documentDate' => self::DOCUMENT_DATE,
            'documentComment' => 'comment.',
            'displayHeader' => true,
            'displayFooter' => true,
            'displayPrices' => true,
            'displayPageCount' => true,
            'displayLineItems' => true,
            'displayLineItemPosition' => true,
            'displayCompanyAddress' => true,
            'displayReturnAddress' => true,
            'displayDivergentDeliveryAddress' => true,
            'companyName' => 'Example Company',
            'companyStreet' => 'Example Street 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Example City',
            'companyPhone' => '+49 555 12345',
            'companyEmail' => 'info@example.com',
            'companyUrl' => 'https://example.com',
            'executiveDirector' => 'Jane Doe',
            'taxNumber' => 'DE123456789',
            'taxOffice' => 'Example Tax Office',
            'vatId' => 'DE987654321',
            'bankName' => 'Example Bank',
            'bankIban' => 'DE89370400440532013000',
            'bankBic' => 'COBADEFFXXX',
            'placeOfJurisdiction' => 'Example Place',
            'placeOfFulfillment' => 'Example Place',
            'pageSize' => 'a4',
            'pageOrientation' => 'portrait',
            'itemsPerPage' => 10,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function buildDemoShippingAddress(string $addressId): array
    {
        return [
            'id' => $addressId,
            'countryId' => $this->getValidCountryId(),
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'john',
            'lastName' => 'doe',
            'street' => 'example street 11',
            'zipcode' => '12345',
            'city' => 'example city',
        ];
    }

    protected function seedDemoBaseConfig(string $documentType): void
    {
        $config = $this->getDemoInvoiceLegacyConfig();
        $config['companyCountryId'] = $this->loadCompanyCountry()->getId();

        $this->upsertBaseConfig($config, $documentType);
    }

    protected function seedReferenceInvoice(
        string $orderId,
        ?string $documentNumber = self::DOCUMENT_NUMBER,
        ?string $orderVersionId = null,
    ): string {
        $documentTypeId = static::getContainer()->get('document_type.repository')->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'invoice')),
            $this->context,
        )->firstId();

        static::assertIsString($documentTypeId);

        $documentId = Uuid::randomHex();

        static::getContainer()->get('document.repository')->create([
            [
                'id' => $documentId,
                'documentTypeId' => $documentTypeId,
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId
                    ?? static::getContainer()->get('order.repository')->createVersion($orderId, $this->context),
                'config' => $documentNumber === null ? [] : ['documentNumber' => $documentNumber],
                'deepLinkCode' => Uuid::randomHex(),
                'static' => false,
                'sent' => false,
            ],
        ], $this->context);

        return $documentId;
    }

    protected function applyTenPercentPromotion(Cart $cart): Cart
    {
        $code = 'TENOFF';

        $this->createTestFixturePercentagePromotion(
            Uuid::randomHex(),
            $code,
            10.0,
            null,
            static::getContainer(),
        );

        $promoLineItem = (new PromotionItemBuilder())->buildPlaceholderItem($code);

        return static::getContainer()
            ->get(CartService::class)
            ->add($cart, $promoLineItem, $this->salesChannelContext);
    }

    protected function enrichOrderForRendering(
        string $orderId,
        string $orderNumber = '10000',
        string $orderDateTime = self::DOCUMENT_DATE,
    ): void {
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        $orderRepository->update([
            [
                'id' => $orderId,
                'orderNumber' => $orderNumber,
                'orderDateTime' => $orderDateTime,
            ],
        ], $this->context);
    }

    protected function loadCompanyCountry(): CountryEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        /** @var EntityRepository<CountryCollection> $repo */
        $repo = static::getContainer()->get('country.repository');
        $country = $repo
            ->search($criteria, $this->context)
            ->getEntities()
            ->first();

        static::assertInstanceOf(CountryEntity::class, $country);

        return $country;
    }
}
