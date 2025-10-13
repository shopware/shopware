<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderInterface;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
class ProductListingDataLoader implements ContentDataLoaderInterface
{
    public function __construct(
        private readonly ProductListingLoader $productListingLoader
    ) {
    }

    public static function getRequirementType(): string
    {
        return 'product_listing';
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context
    ): mixed {
        $config = $requirement->config;

        if (!$config instanceof ProductListingLoaderConfig) {
            return null;
        }

        $criteria = $this->buildCriteria($element, $config);

        return $this->productListingLoader->load($criteria, $context);
    }

    /**
     * Element properties can override requirement config (limit, page, associations).
     */
    private function buildCriteria(ContentElement $element, ProductListingLoaderConfig $config): Criteria
    {
        $criteria = new Criteria();

        $limit = $element->getProperty('limit') ?? $config->limit;
        if (\is_int($limit) && $limit > 0) {
            $criteria->setLimit($limit);
        }

        $page = $element->getProperty('page');
        if (\is_int($page) && $page > 0) {
            $offset = ($page - 1) * ($limit ?? 24);
            $criteria->setOffset($offset);
        }

        foreach ($config->associations as $association) {
            $criteria->addAssociation($association);
        }

        $elementAssociations = $element->getProperty('associations');
        if (\is_array($elementAssociations)) {
            foreach ($elementAssociations as $association) {
                if (\is_string($association)) {
                    $criteria->addAssociation($association);
                }
            }
        }

        return $criteria;
    }
}
