<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<ProductListingResult>
 */
#[Package('inventory')]
class ProductSearchDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'product_search';

    public function __construct(
        private readonly AbstractProductSearchRoute $searchRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof ProductSearchLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $propertyName = $config->searchTermProperty ?? 'searchTerm';
        $searchTerm = $element->getProperty($propertyName);

        if (!\is_string($searchTerm) || $searchTerm === '') {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = $this->buildCriteria($element, $config);

        $searchRequest = new Request();
        $searchRequest->request->set('search', $searchTerm);

        $response = $this->searchRoute->load($searchRequest, $context, $criteria);

        return ContentDataLoaderResult::cachedExternally($response->getListingResult());
    }

    private function buildCriteria(ContentElement $element, ProductSearchLoaderConfig $config): Criteria
    {
        $criteria = new Criteria();

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
