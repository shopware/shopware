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
            $criteria->addState(self::STATE_DISPLAY_AS_GROUP_DISABLED);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use enrichCriteria instead
     *
     * @return array<int, Filter>
     */
    public function buildFilters(string $id, Context $context): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', AbstractProductStreamBuilder::class . '::enrichCriteria')
        );

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
