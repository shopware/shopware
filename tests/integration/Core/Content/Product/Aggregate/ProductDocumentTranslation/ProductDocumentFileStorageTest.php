<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocumentTranslation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentFileStorage;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentFileStorageTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private ProductDocumentFileStorage $storage;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->storage = static::getContainer()->get(ProductDocumentFileStorage::class);

        static::getContainer()->get('media.repository')->create(
            array_map(fn (string $name): array => ['id' => $this->ids->create($name), 'fileName' => $name, 'fileExtension' => 'pdf', 'mimeType' => 'application/pdf'], ['manual', 'handbuch']),
            Context::createDefaultContext(),
        );
        $product = (new ProductBuilder($this->ids, 'drill'))->price(10)->build();
        $product['documents'] = [[
            'id' => $this->ids->create('document'),
            'title' => 'Manual',
            'mediaId' => $this->ids->get('manual'),
            'translations' => [$this->getDeDeLanguageId() => ['mediaId' => $this->ids->get('handbuch')]],
        ]];
        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
    }

    public function testReadsTheDefaultFileOfEachDocument(): void
    {
        static::assertSame(
            [$this->ids->get('document') => $this->ids->get('manual')],
            $this->storage->getDefaultFiles([$this->ids->get('document')], Defaults::LIVE_VERSION),
        );
    }
}
