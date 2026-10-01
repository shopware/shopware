<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Template\TemplateContext;
use Shopware\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TradePartyView;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\CollidingRenderData;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticRenderData;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TemplateContext::class)]
class TemplateContextTest extends TestCase
{
    public function testExposesDocumentCompanyInfoFields(): void
    {
        $context = $this->createContext();

        static::assertSame('company', $context->companyName);
        static::assertSame('example street 10', $context->companyStreet);
        static::assertSame('12345', $context->companyZipcode);
        static::assertSame('example city', $context->companyCity);
    }

    public function testExposesDocumentConfigFields(): void
    {
        $context = $this->createContext();

        static::assertSame('a4', $context->pageSize);
        static::assertSame('landscape', $context->pageOrientation);
        static::assertSame(10, $context->itemsPerPage);
        static::assertFalse($context->displayHeader);
    }

    public function testExposesRenderDataFields(): void
    {
        $context = $this->createContext();

        static::assertSame('date', $context->documentDate);
        static::assertSame('number', $context->documentNumber);
        static::assertSame('comment', $context->documentComment);
        static::assertFalse($context->offsetGet('intraCommunityDelivery'));
    }

    public function testFlattensPublicFieldsOfAdditionalTypeDataIntoNamespace(): void
    {
        $meta = new DocumentMetaRenderData(
            config: new DocumentConfig('a4', 'landscape', 10),
            company: new DocumentCompanyInfo('company', 'street', '12345', 'city', new CountryEntity()),
            display: new DocumentDisplayOptions(),
            documentDate: 'date',
            documentNumber: 'number',
            documentComment: null,
        );

        $context = new TemplateContext($meta, [new StaticRenderData('from-plugin')]);

        static::assertSame('from-plugin', $context->offsetGet('testData'));
    }

    public function testThrowsWhenTypeDataShadowsSharedProperty(): void
    {
        $meta = new DocumentMetaRenderData(
            config: new DocumentConfig('a4', 'landscape', 10),
            company: new DocumentCompanyInfo('company', 'street', '12345', 'city', new CountryEntity()),
            display: new DocumentDisplayOptions(),
            documentDate: 'date',
            documentNumber: 'number',
            documentComment: null,
        );

        $this->expectExceptionObject(DocumentV2Exception::templateContextPropertyCollision('companyName'));

        new TemplateContext($meta, [new CollidingRenderData()]);
    }

    public function testFallsBackToLegacyConfigForKeysNotPromotedToTypedProperties(): void
    {
        $context = $this->createContext(legacyConfig: ['displayAdditionalNoteDelivery' => true]);

        static::assertTrue($context->displayAdditionalNoteDelivery);
    }

    public function testTypedPropertiesWinOverLegacyConfig(): void
    {
        $context = $this->createContext(legacyConfig: ['companyName' => 'legacy']);

        static::assertSame('company', $context->companyName);
    }

    public function testReturnsNullForUnknownKey(): void
    {
        $context = $this->createContext();

        static::assertNull($context->offsetGet('doesNotExist'));
    }

    public function testIssetReportsKnownAndUnknownKeys(): void
    {
        $context = $this->createContext(legacyConfig: ['displayAdditionalNoteDelivery' => true]);

        static::assertTrue($context->offsetExists('companyName'));
        static::assertTrue($context->offsetExists('pageSize'));
        static::assertTrue($context->offsetExists('documentDate'));
        static::assertTrue($context->offsetExists('displayAdditionalNoteDelivery'));
        static::assertFalse($context->offsetExists('doesNotExist'));
    }

    public function testArrayAccessMirrorsPropertyAccess(): void
    {
        $context = $this->createContext();

        static::assertSame($context->companyName, $context->offsetGet('companyName'));
        static::assertSame($context->pageSize, $context->offsetGet('pageSize'));
        static::assertSame($context->itemsPerPage, $context->offsetGet('itemsPerPage'));
        static::assertNull($context->offsetGet('doesNotExist'));

        static::assertTrue($context->offsetExists('companyName'));
        static::assertFalse($context->offsetExists('doesNotExist'));
    }

    public function testOffsetSetThrows(): void
    {
        $context = $this->createContext();

        $this->expectExceptionObject(DocumentV2Exception::templateContextReadOnly('companyName'));

        $context->offsetSet('companyName', 'mutated');
    }

    public function testOffsetUnsetThrows(): void
    {
        $context = $this->createContext();

        $this->expectExceptionObject(DocumentV2Exception::templateContextReadOnly('companyName'));

        $context->offsetUnset('companyName');
    }

    /**
     * @param array<string, mixed> $legacyConfig
     */
    private function createContext(array $legacyConfig = []): TemplateContext
    {
        $meta = new DocumentMetaRenderData(
            config: new DocumentConfig(
                'a4',
                'landscape',
                10
            ),
            company: new DocumentCompanyInfo(
                'company',
                'example street 10',
                '12345',
                'example city',
                new CountryEntity()
            ),
            display: new DocumentDisplayOptions(),
            documentDate: 'date',
            documentNumber: 'number',
            documentComment: 'comment',
            legacyConfig: $legacyConfig,
        );

        $renderData = new InvoiceRenderData(
            typeCode: TypeCode::INVOICE,
            buyerReference: '',
            buyer: new TradePartyView(
                id: null,
                name: '',
                street: null,
                additionalAddressLine1: null,
                additionalAddressLine2: null,
                zipcode: null,
                city: null,
                countrySubdivision: null,
                countryIso: null,
                email: null,
            ),
            deliveryDate: null,
            lineItems: [],
            allowanceCharges: [],
            taxBreakdown: [],
            monetarySummation: new MonetarySummationView(
                0,
                0,
                0,
                0,
                0,
                'EUR',
                0,
                0,
                0,
                0
            ),
            paymentMeans: null,
            paymentDueDate: null,
            intraCommunityDelivery: false,
        );

        return new TemplateContext($meta, [$renderData]);
    }
}
