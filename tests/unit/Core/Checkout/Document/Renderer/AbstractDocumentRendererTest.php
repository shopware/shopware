<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AbstractDocumentRenderer::class)]
class AbstractDocumentRendererTest extends TestCase
{
    private CustomDomesticSupplyRenderer $renderer;

    private VatIdPatternProvider $vatIdPatternProvider;

    private OrderEntity $order;

    protected function setUp(): void
    {
        $sellerCountry = (new CountryEntity())->assign([
            'id' => Uuid::randomHex(),
            'iso' => 'DE',
            'isEu' => true,
            'vatIdPattern' => 'DE\\d{9}',
        ]);

        $this->renderer = new CustomDomesticSupplyRenderer();
        $this->vatIdPatternProvider = new VatIdPatternProvider(
            new StaticEntityRepository([new CountryCollection([$sellerCountry])]),
            new StaticSystemConfigService(['core.basicInformation.sellerCountryId' => $sellerCountry->getId()]),
        );
        $this->order = new OrderEntity();
        $this->order->setSalesChannelId(Uuid::randomHex());
    }

    #[DataProvider('deliveryCountryProvider')]
    public function testCustomRendererUsesThePrimaryDelivery(?string $deliveryCountryIso, bool $expected): void
    {
        $this->order->setPrimaryOrderDelivery($this->createDelivery($deliveryCountryIso));
        $otherDelivery = $this->createDelivery($expected ? 'FR' : 'DE');
        static::assertNotNull($otherDelivery);
        $this->order->setDeliveries(new OrderDeliveryCollection([$otherDelivery]));

        static::assertSame($expected, $this->renderer->usesDomesticSupply($this->order, $this->vatIdPatternProvider));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove when renderers always use the primary delivery
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    #[DataProvider('deliveryCountryProvider')]
    public function testCustomRendererUsesTheFirstLegacyDelivery(?string $deliveryCountryIso, bool $expected): void
    {
        $this->order->setPrimaryOrderDelivery($this->createDelivery($expected ? 'FR' : 'DE'));
        $delivery = $this->createDelivery($deliveryCountryIso);
        $this->order->setDeliveries(new OrderDeliveryCollection($delivery === null ? [] : [$delivery]));

        static::assertSame($expected, $this->renderer->usesDomesticSupply($this->order, $this->vatIdPatternProvider));
    }

    public function testSellerVatDoesNotMakeAForeignDeliveryDomestic(): void
    {
        $delivery = $this->createDelivery('FR');
        static::assertNotNull($delivery);
        $this->order->setPrimaryOrderDelivery($delivery);
        $this->order->setDeliveries(new OrderDeliveryCollection([$delivery]));
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setVatIds(['DE123456789']);
        $this->order->setOrderCustomer($orderCustomer);

        static::assertFalse($this->renderer->usesDomesticSupply($this->order, $this->vatIdPatternProvider));
    }

    /**
     * @return iterable<string, array{string|null, bool}>
     */
    public static function deliveryCountryProvider(): iterable
    {
        yield 'delivery in the seller country is domestic' => ['DE', true];
        yield 'delivery in another member state is not domestic' => ['FR', false];
        yield 'missing delivery does not use the other delivery source' => [null, false];
    }

    private function createDelivery(?string $countryIso): ?OrderDeliveryEntity
    {
        if ($countryIso === null) {
            return null;
        }

        $country = new CountryEntity();
        $country->setIso($countryIso);
        $address = new OrderAddressEntity();
        $address->setCountry($country);
        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setShippingOrderAddress($address);

        return $delivery;
    }
}

/**
 * @internal
 */
#[Package('after-sales')]
class CustomDomesticSupplyRenderer extends AbstractDocumentRenderer
{
    public function usesDomesticSupply(OrderEntity $order, VatIdPatternProvider $vatIdPatternProvider): bool
    {
        return parent::isDomesticSupply($order, $vatIdPatternProvider);
    }

    public function supports(): string
    {
        return 'custom';
    }

    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        return new RendererResult();
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        return $this;
    }
}
