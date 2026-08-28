<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Content\ProductStream\ProductStreamException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductStreamBuilder extends AbstractProductStreamBuilder implements ProductStreamBuilderInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductStreamCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly EntityDefinition $productDefinition
    ) {
    }

    public function enrichCriteria(Criteria $criteria, string $id, Context $context): void
    {
        $stream = $this->loadStream($id, $context);
        $criteria->addFilter(...$this->parseFilters($stream, $id));

        if (!$stream->isDisplayAsGroup()) {
            $criteria->addState(ProductListingLoader::STATE_SKIP_ADD_GROUPING);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use AbstractProductStreamBuilder::enrichCriteria instead.
     */
    public function buildFilters(string $id, Context $context): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'AbstractProductStreamBuilder::enrichCriteria')
        );

        return $this->parseFilters($this->loadStream($id, $context), $id);
    }

    private function loadStream(string $id, Context $context): ProductStreamEntity
    {
        $criteria = new Criteria([$id]);

        $stream = $this->repository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);

        if (!$stream) {
            throw ProductStreamException::productStreamNotFound($id);
        }

        return $stream;
    }

    /**
     * @return list<Filter>
     */
    private function parseFilters(ProductStreamEntity $stream, string $id): array
    {
        $data = $stream->getApiFilter();
        if (!$data) {
            // Empty api_filter ([]) on a valid stream means all filters were removed; a broken/invalid
            // stream (api_filter null or invalid) stays a NoFilterException so callers can tell them apart.
            if ($data === [] && !$stream->isInvalid()) {
                throw ProductStreamException::emptyProductStream($id);
            }

            throw ProductStreamException::noFilters($id);
        }

        $filters = [];
        $exception = new SearchRequestException();

        foreach ($data as $filter) {
            $filters[] = QueryStringParser::fromArray($this->productDefinition, $filter, $exception, '');
        }

        return $filters;
    }
}
