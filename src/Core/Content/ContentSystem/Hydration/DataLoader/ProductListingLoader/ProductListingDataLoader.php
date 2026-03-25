<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\UnicodeString;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ProductListingDataLoader extends AbstractContentDataLoader
{
    public function __construct(
        private readonly AbstractProductListingRoute $listingRoute
    ) {
    }

    public function getDecorated(): AbstractContentDataLoader
    {
        throw new DecorationPatternException(self::class);
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
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof ProductListingLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $propertyName = $config->property ?? 'navigationId';
        $navigationId = $element->getProperty($propertyName);

        if (!\is_string($navigationId)) {
            return ContentDataLoaderResult::notFound();
        }

        $navigationId = (new UnicodeString($navigationId))->lower()->toString();

        $criteria = $this->buildCriteria($element, $config);

        $response = $this->listingRoute->load($navigationId, $request, $context, $criteria);
        $result = $response->getResult();

        // ProductListingRoute internally adds cache tags via CacheTagCollector
        return ContentDataLoaderResult::cachedExternally($result);
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
