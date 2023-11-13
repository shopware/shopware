<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Exception\EmptyExportException;
use Shopware\Core\Content\ProductExport\Exception\RenderFooterException;
use Shopware\Core\Content\ProductExport\Exception\RenderHeaderException;
use Shopware\Core\Content\ProductExport\Exception\RenderProductException;
use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\ProductExport\ProductExportException
 */
class ProductExportExceptionTest extends TestCase
{
    public function testTemplateBodyNotSet(): void
    {
        $exception = ProductExportException::templateBodyNotSet();
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('PRODUCT_EXPORT__TEMPLATE_BODY_NOT_SET', $exception->getErrorCode());

        $this->expectException(ProductExportException::class);

        throw $exception;
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testRenderFooterException(): void
    {
        $exception = ProductExportException::renderFooterException('Footer!');
        static::assertSame('Failed rendering string template using Twig: Footer!', $exception->getMessage());

        $this->expectException(RenderFooterException::class);

        throw $exception;
    }

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @deprecated tag:v6.6.0.0 - will be removed
     */
    public function testRenderHeaderException(): void
    {
        $exception = ProductExportException::renderHeaderException('Header!');
        static::assertSame('Failed rendering string template using Twig: Header!', $exception->getMessage());

        $this->expectException(RenderHeaderException::class);

        throw $exception;
    }

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @deprecated tag:v6.6.0.0 - will be removed
     */
    public function testRenderProductException(): void
    {
        $exception = ProductExportException::renderProductException('Product!');
        static::assertSame('Failed rendering string template using Twig: Product!', $exception->getMessage());

        $this->expectException(RenderProductException::class);

        throw $exception;
    }

    public function testRenderFooterException660(): void
    {
        $exception = ProductExportException::renderFooterException('Footer!');
        static::assertSame('Failed rendering string template using Twig: Footer!', $exception->getMessage());

        $this->expectException(ProductExportException::class);

        throw $exception;
    }

    public function testRenderHeaderException660(): void
    {
        $exception = ProductExportException::renderHeaderException('Header!');
        static::assertSame('Failed rendering string template using Twig: Header!', $exception->getMessage());

        $this->expectException(ProductExportException::class);

        throw $exception;
    }

    public function testRenderProductException660(): void
    {
        $exception = ProductExportException::renderProductException('Product!');
        static::assertSame('Failed rendering string template using Twig: Product!', $exception->getMessage());

        $this->expectException(ProductExportException::class);

        throw $exception;
    }

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @deprecated tag:v6.6.0.0 - will be removed
     */
    public function testProductExportNotFoundLegacy(): void
    {
        $exception = ProductExportException::productExportNotFound('product-id');

        static::assertInstanceOf(EmptyExportException::class, $exception);
    }

    public function testProductExportNotFound(): void
    {
        $exception = ProductExportException::productExportNotFound('product-id');

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(ProductExportException::PRODUCT_EXPORT_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find products for export with id "product-id"', $exception->getMessage());

        $exception = ProductExportException::productExportNotFound();
        static::assertSame('No products for export found', $exception->getMessage());
    }
}
