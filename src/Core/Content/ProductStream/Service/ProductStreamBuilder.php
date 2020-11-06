<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;

class ProductStreamBuilder implements ProductStreamBuilderInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var EntityDefinition
     */
    private $productDefinition;

    public function __construct(EntityRepositoryInterface $productStreamRepository, EntityDefinition $productDefinition)
    {
        $this->repository = $productStreamRepository;
        $this->productDefinition = $productDefinition;
    }

    public function buildFilters(string $id, Context $context): array
    {
        $criteria = new Criteria([$id]);

        /** @var ProductStreamEntity|null $stream */
        $stream = $this->repository
            ->search($criteria, $context)
            ->get($id);

        if (!$stream) {
            throw new EntityNotFoundException('product_stream', $id);
        }

        $data = $stream->getApiFilter();
        if (!$data) {
            throw new NoFilterException($id);
        }

        $filters = [];
        $exception = new SearchRequestException();

        foreach ($data as $filter) {
            $filters[] = QueryStringParser::fromArray($this->productDefinition, $filter, $exception, '');
        }

        if (empty($filters)) {
            throw new NoFilterException($id);
        }

        return $filters;
    }
}
