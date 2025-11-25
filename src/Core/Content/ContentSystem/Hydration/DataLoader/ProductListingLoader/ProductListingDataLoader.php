<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderInterface;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\UnicodeString;

/**
 * @internal
 */
#[Package('discovery')]
class ProductListingDataLoader implements ContentDataLoaderInterface
{
    public function __construct(
        private readonly AbstractProductListingRoute $listingRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return 'product_listing';
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ?ProductListingResult {
        $config = $requirement->config;

        if (!$config instanceof ProductListingLoaderConfig) {
            return null;
        }

        $propertyName = $config->property ?? 'navigationId';
        $navigationId = $element->getProperty($propertyName);

        if ($navigationId === null) {
            return null;
        }

        if (!\is_string($navigationId)) {
            return null;
        }

        $navigationId = (new UnicodeString($navigationId))->lower()->toString();

        $criteria = $this->buildCriteria($element, $config);

        $response = $this->listingRoute->load($navigationId, $request, $context, $criteria);

        return $response->getResult();
    }

    /**
     * Element properties can override requirement config associations.
     */
    private function buildCriteria(ContentElement $element, ProductListingLoaderConfig $config): Criteria
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
