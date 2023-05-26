<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\HttpFoundation\Request;

#[Package('core')]
class ProductSearchBuilder implements ProductSearchBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ProductSearchBuilderInterface $decorated,
        private readonly ElasticsearchHelper $helper,
        private readonly ProductDefinition $productDefinition
    ) {
    }

    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$this->helper->allowSearch($this->productDefinition, $context->getContext(), $criteria)) {
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
            throw RoutingException::missingRequestParameter('search');
        }

        // reset queries and set term to criteria.
        $criteria->resetQueries();

        // elasticsearch will interpret this on demand
        $criteria->setTerm($term);
    }
}
