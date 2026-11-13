<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductStreamHasDocumentsTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();

        $this->clearElasticsearch();
        $this->createProducts();

        static::getContainer()->get(ElasticsearchIndexingCommand::class)->run(new ArrayInput(['--no-queue' => true]), new NullOutput());

        $this->createDocuments();
        $this->refreshIndex();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get('product.repository')->delete([
            ['id' => $this->ids->get('plain')],
            ['id' => $this->ids->get('manual')],
            ['id' => $this->ids->get('drill')],
        ], $this->context);

        static::getContainer()->get('media.repository')->delete([['id' => $this->ids->get('file')]], $this->context);

        if ($this->ids->has('stream')) {
            static::getContainer()->get('product_stream.repository')->delete([['id' => $this->ids->get('stream')]], $this->context);
        }
    }

    public function testDalFindsProductsWithDocumentsIncludingInheritingVariants(): void
    {
        static::assertEqualsCanonicalizing(
            [$this->ids->get('manual'), $this->ids->get('drill.blue')],
            $this->searchDal($this->createStream(hasDocuments: true))
        );
    }

    public function testDalFindsProductsWithoutDocuments(): void
    {
        static::assertSame([$this->ids->get('plain')], $this->searchDal($this->createStream(hasDocuments: false)));
    }

    public function testElasticsearchFindsProductsWithDocumentsIncludingInheritingVariants(): void
    {
        static::assertEqualsCanonicalizing(
            [$this->ids->get('manual'), $this->ids->get('drill.blue')],
            $this->searchElasticsearch($this->createStream(hasDocuments: true))
        );
    }

    public function testElasticsearchFindsProductsWithoutDocuments(): void
    {
        static::assertSame([$this->ids->get('plain')], $this->searchElasticsearch($this->createStream(hasDocuments: false)));
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    protected function runWorker(): void
    {
    }

    private function createProducts(): void
    {
        static::getContainer()->get('media.repository')->create([[
            'id' => $this->ids->create('file'),
            'fileName' => 'manual-' . $this->ids->get('file'),
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
        ]], $this->context);

        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($this->ids, 'plain'))->price(10)->visibility()->build(),
            (new ProductBuilder($this->ids, 'manual'))->price(10)->visibility()->build(),
            (new ProductBuilder($this->ids, 'drill'))
                ->price(10)
                ->visibility()
                ->variant((new ProductBuilder($this->ids, 'drill.blue'))->build())
                ->build(),
        ], $this->context);
    }

    private function createDocuments(): void
    {
        static::getContainer()->get('product_document.repository')->create([
            ['productId' => $this->ids->get('manual'), 'title' => 'Manual', 'mediaId' => $this->ids->get('file')],
            ['productId' => $this->ids->get('drill'), 'title' => 'Manual', 'mediaId' => $this->ids->get('file')],
        ], $this->context);
    }

    private function createStream(bool $hasDocuments): string
    {
        $documentsAreEmpty = ['type' => 'equals', 'field' => 'documents.id', 'value' => null];

        static::getContainer()->get('product_stream.repository')->create([[
            'id' => $this->ids->create('stream'),
            'name' => 'has documents',
            'filters' => [
                $hasDocuments
                    ? ['type' => 'not', 'operator' => 'AND', 'queries' => [$documentsAreEmpty]]
                    : $documentsAreEmpty,
            ],
        ]], $this->context);

        return $this->ids->get('stream');
    }

    /**
     * @return list<string>
     */
    private function searchDal(string $streamId): array
    {
        $criteria = $this->createCriteria($streamId);

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        /** @var list<string> $ids */
        $ids = static::getContainer()->get('product.repository')->searchIds($criteria, $context)->getIds();

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function searchElasticsearch(string $streamId): array
    {
        $criteria = $this->createCriteria($streamId);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        /** @var list<string> $ids */
        $ids = $this->createEntitySearcher()
            ->search(static::getContainer()->get(ProductDefinition::class), $criteria, $this->context)
            ->getIds();

        return $ids;
    }

    private function createCriteria(string $streamId): Criteria
    {
        $criteria = new Criteria();
        static::getContainer()->get(ProductStreamBuilder::class)->enrichCriteria($criteria, $streamId, $this->context);

        $criteria->addFilter(new EqualsAnyFilter('id', [
            $this->ids->get('plain'),
            $this->ids->get('manual'),
            $this->ids->get('drill.blue'),
        ]));

        return $criteria;
    }
}
