<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Config\CompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Twig\TemplateContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TemplateContext::class)]
class TemplateContextTest extends TestCase
{
    public function testExposesCompanyInfoFields(): void
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
        static::assertFalse($context->intraCommunityDelivery);
    }

    public function testExposesOverrides(): void
    {
        $context = $this->createContext(fileType: 'html', itemsPerPage: 1000);

        static::assertSame('html', $context->fileType);
        static::assertSame(1000, $context->itemsPerPage);
    }

    public function testRendererValuesTakePrecedenceOverDocumentConfig(): void
    {
        $context = $this->createContext(
            fileType: 'html',
            itemsPerPage: 1000,
            legacyConfig: [
                'fileType' => 'pdf',
                'itemsPerPage' => 10,
            ],
        );

        static::assertSame('html', $context->fileType);
        static::assertSame(1000, $context->itemsPerPage);
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
        $context = $this->createContext(fileType: 'html');

        static::assertSame($context->companyName, $context->offsetGet('companyName'));
        static::assertSame($context->pageSize, $context->offsetGet('pageSize'));
        static::assertSame($context->fileType, $context->offsetGet('fileType'));
        static::assertNull($context->offsetGet('doesNotExist'));

        static::assertTrue($context->offsetExists('companyName'));
        static::assertFalse($context->offsetExists('doesNotExist'));
    }

    public function testOffsetSetThrows(): void
    {
        $context = $this->createContext();

        static::expectExceptionObject(DocumentV2Exception::templateContextReadOnly('companyName'));

        $context->offsetSet('companyName', 'mutated');
    }

    public function testOffsetUnsetThrows(): void
    {
        $context = $this->createContext();

        static::expectExceptionObject(DocumentV2Exception::templateContextReadOnly('companyName'));

        $context->offsetUnset('companyName');
    }

    /**
     * @param array<string, mixed> $legacyConfig
     */
    private function createContext(
        ?string $fileType = null,
        ?int $itemsPerPage = null,
        array $legacyConfig = [],
    ): TemplateContext {
        $renderData = new InvoiceRenderData(
            new DocumentConfig('a4', 'landscape', 10),
            new CompanyInfo('company', 'example street 10', '12345', 'example city', new CountryEntity()),
            'date',
            'number',
            'comment',
            false,
            false,
            false,
            false,
            false,
            [],
            $legacyConfig,
        );

        return new TemplateContext(
            $renderData,
            $fileType,
            $itemsPerPage
        );
    }
}
