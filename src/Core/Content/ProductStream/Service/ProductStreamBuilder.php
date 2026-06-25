<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductStreamBuilder implements ProductStreamBuilderInterface, ProductStreamCriteriaEnricher
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
            $criteria->addState(ProductStreamCriteriaEnricher::STATE_DISPLAY_AS_GROUP_DISABLED);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-interface - Will be removed, use ProductStreamCriteriaEnricher::enrichCriteria instead.
     *      Intentionally does not call Feature::triggerDeprecationOrThrow: this method is still invoked by core
     *      listing consumers as a backward-compatible fallback for builders that do not implement
     *      ProductStreamCriteriaEnricher, so a runtime deprecation here would fire from inside the core.
     *
     * @return array<int, Filter>
     */
    public function buildFilters(string $id, Context $context): array
    {
        return $this->parseFilters($this->loadStream($id, $context), $id);
    }

    private function loadStream(string $id, Context $context): ProductStreamEntity
    {
        $criteria = new Criteria([$id]);

        /** @var ProductStreamEntity|null $stream */
        $stream = $this->repository
            ->search($criteria, $context)
            ->get($id);

        if (!$stream) {
            throw new EntityNotFoundException('product_stream', $id);
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
            throw new NoFilterException($id);
        }

        $filters = [];
        $exception = new SearchRequestException();

        foreach ($data as $filter) {
            $filters[] = QueryStringParser::fromArray($this->productDefinition, $filter, $exception, '');
        }

        return $filters;
    }
}
