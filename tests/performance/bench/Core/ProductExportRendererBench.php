<?php declare(strict_types=1);

namespace Shopware\Tests\Bench\Core;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExportRenderer;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Bench\AbstractBenchCase;

/**
 * @internal - only for performance benchmarks
 */
class ProductExportRendererBench extends AbstractBenchCase
{
    private ProductExportRenderer $renderer;

    private ProductExportEntity $productExport;

    private ProductEntity $product;

    /**
     * @var array<string, mixed>
     */
    private array $data;

    #[BeforeMethods(['setUpScalarContextBenchmark'])]
    #[AfterMethods(['tearDown'])]
    public function bench_render_body_with_scalar_context(): void
    {
        $this->renderer->renderBody($this->productExport, $this->context, $this->data);
    }

    #[BeforeMethods(['setUpWithoutUrlBenchmark'])]
    #[AfterMethods(['tearDown'])]
    public function bench_render_body_without_urls(): void
    {
        $this->renderer->renderBody($this->productExport, $this->context, $this->data);
    }

    #[BeforeMethods(['setUpWithNestedUrlBenchmark'])]
    #[AfterMethods(['tearDown'])]
    public function bench_render_body_with_nested_url(): void
    {
        $this->renderer->renderBody($this->productExport, $this->context, $this->data);
    }

    public function setUpScalarContextBenchmark(): void
    {
        $this->setUpRendererBenchmark();
        $this->productExport->setBodyTemplate('{{ name }}');
        $this->data = ['name' => 'Simple product'];
    }

    public function setUpWithoutUrlBenchmark(): void
    {
        $this->setUpBenchmark('Product image.jpg');
    }

    public function setUpWithNestedUrlBenchmark(): void
    {
        $this->setUpBenchmark('https://example.com/media/Product image.jpg');
    }

    private function setUpBenchmark(string $mediaUrl): void
    {
        $this->setUpRendererBenchmark();
        $this->productExport->setBodyTemplate('{{ product.name }}');

        $this->product = new ProductEntity();
        $this->product->setId(Uuid::randomHex());
        $this->product->setName('Simple product');

        $media = new MediaEntity();
        $media->setUrl($mediaUrl);

        $cover = new ProductMediaEntity();
        $cover->setMedia($media);

        $this->product->setCover($cover);
        $this->product->addExtension('benchmark', new ArrayStruct([
            'nodes' => array_map(
                static fn (int $index): ArrayStruct => new ArrayStruct([
                    'identifier' => 'node-' . $index,
                    'metadata' => new ArrayStruct([
                        'label' => 'Benchmark node ' . $index,
                        'value' => $index,
                    ]),
                ]),
                range(1, 100)
            ),
        ]));

        $this->data = ['product' => $this->product];
    }

    private function setUpRendererBenchmark(): void
    {
        parent::setUp();

        $this->renderer = static::getContainer()->get(ProductExportRenderer::class);
        $this->productExport = new ProductExportEntity();
    }
}
