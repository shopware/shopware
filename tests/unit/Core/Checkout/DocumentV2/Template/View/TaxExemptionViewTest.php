<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TaxCategory;
use Shopware\Core\Checkout\DocumentV2\Template\View\TaxExemptionView;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TaxExemptionView::class)]
class TaxExemptionViewTest extends TestCase
{
    public function testAnIntraCommunitySupplyIsCategoryKWithTheBuyerVatIdAndTheDeliveryAddress(): void
    {
        $address = self::address('75001');

        $view = TaxExemptionView::fromIntraCommunitySupply('FR12345678901', $address, 'DE123456789');

        static::assertNotNull($view);
        static::assertSame(TaxCategory::INTRA_COMMUNITY_SUPPLY, $view->taxCategory);
        static::assertSame('FR12345678901', $view->buyerVatId);
        static::assertSame($address, $view->deliveryAddress);
    }

    #[DataProvider('incompleteIntraCommunitySupplyProvider')]
    public function testAnIntraCommunitySupplyWithoutTheDataOfCategoryKIsNoExemption(?string $buyerVatId, ?OrderAddressEntity $address, ?string $sellerVatId): void
    {
        static::assertNull(TaxExemptionView::fromIntraCommunitySupply($buyerVatId, $address, $sellerVatId));
    }

    public static function incompleteIntraCommunitySupplyProvider(): \Generator
    {
        yield 'no intra-community delivery, so no buyer VAT ID' => [null, self::address('75001'), 'DE123456789'];
        yield 'no delivery address' => ['FR12345678901', null, 'DE123456789'];
        yield 'a delivery address without zip code' => ['FR12345678901', self::address(null), 'DE123456789'];
        yield 'a delivery address with an empty zip code' => ['FR12345678901', self::address(''), 'DE123456789'];
        yield 'no seller VAT ID in the document settings' => ['FR12345678901', self::address('75001'), null];
        yield 'an empty seller VAT ID in the document settings' => ['FR12345678901', self::address('75001'), ''];
    }

    private static function address(?string $zipcode): OrderAddressEntity
    {
        $address = new OrderAddressEntity();
        $address->setZipcode($zipcode);

        return $address;
    }
}
