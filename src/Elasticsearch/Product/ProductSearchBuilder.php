<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\HttpFoundation\Request;

class ProductSearchBuilder implements ProductSearchBuilderInterface
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

        $search = $request->get('search');

        if (\is_array($search)) {
            $term = implode(' ', $search);
        } else {
            $term = (string) $search;
        }

        $term = trim($term);

        if (empty($term)) {
            throw new MissingRequestParameterException('search');
        }

        // reset queries and set term to criteria.
        $criteria->resetQueries();

        // elasticsearch will interpret this on demand
        $criteria->setTerm($term);
    }
}
