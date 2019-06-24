<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\HttpFoundation\Request;

class ProductElasticsearchSearchBuilder implements ProductSearchBuilderInterface
{
    /**
     * @var ProductSearchBuilderInterface
     */
    private $decorated;

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    public function __construct(
        ProductSearchBuilderInterface $decorated,
        ElasticsearchHelper $helper,
        ProductDefinition $productDefinition
    ) {
        $this->decorated = $decorated;
        $this->helper = $helper;
        $this->productDefinition = $productDefinition;
    }

    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$this->helper->allowSearch($this->productDefinition, $context->getContext())) {
            $this->decorated->build($request, $criteria, $context);

            return;
        }

        $term = trim((string) $request->query->get('search'));

        if (empty($term)) {
            throw new MissingRequestParameterException('search');
        }

        $fields = [
            'product.name' => 1000,
            'product.productNumber' => 1000,
            'product.ean' => 800,
            'product.manufacturer.name' => 200,
        ];

        foreach ($fields as $field => $boost) {
            $criteria->addQuery(
                new ScoreQuery(new ContainsFilter($field, $term), $boost)
            );
        }
    }
}
