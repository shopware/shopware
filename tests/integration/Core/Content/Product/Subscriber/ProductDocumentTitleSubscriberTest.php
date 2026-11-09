<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentTitleSubscriberTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testADocumentSavedWithoutTitleIsNamedAfterItsFile(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        static::getContainer()->get('product.repository')->create([(new ProductBuilder($ids, 'drill'))->price(10)->build()], $context);
        static::getContainer()->get('media.repository')->create([
            ['id' => $ids->create('file'), 'fileName' => 'operating-manual', 'fileExtension' => 'pdf', 'fileSize' => 1024],
        ], $context);

        /** @var EntityRepository<ProductDocumentCollection> $repository */
        $repository = static::getContainer()->get('product_document.repository');
        $repository->create([['id' => $ids->create('document'), 'productId' => $ids->get('drill'), 'mediaId' => $ids->get('file')]], $context);

        $document = $repository->search(new Criteria([$ids->get('document')]), $context)->getEntities()->first();
        static::assertSame('operating-manual', $document?->getTitle());
    }
}
