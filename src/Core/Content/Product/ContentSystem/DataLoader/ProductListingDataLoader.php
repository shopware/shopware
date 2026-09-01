<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<ProductListingResult>
 */
#[Package('framework')]
class ProductListingDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'product_listing';

    public function __construct(
        private readonly AbstractProductListingRoute $listingRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'navigationId'),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'associations', referencedType: 'list<string>', mergesInto: 'associations'),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $navigationId = $inputs->stringOrNull('property');

        if ($navigationId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $navigationId = u($navigationId)->lower()->toString();

        // A PropertyReference value passes LoaderInputResolver::dereference()'s string type check untouched, so
        // an unsubstituted template placeholder (e.g. "{{categoryId}}" left literal on a layout not rooted on a
        // category) reaches here as-is. Anything but an id therefore degrades rather than reaching
        // Uuid::fromHexToBytes() when ProductListingRoute searches the category by id.
        if (!Uuid::isValid($navigationId)) {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = $this->buildCriteria($inputs);

        try {
            $response = $this->listingRoute->load($navigationId, $request, $context, $criteria);
        } catch (ProductException|EntityNotFoundException|NoFilterException) {
            return ContentDataLoaderResult::notFound();
        }

        // ProductListingRoute internally adds cache tags via CacheTagCollector
        return ContentDataLoaderResult::cachedExternally($response->getResult());
    }

    /**
     * The `associationOverride` reference is already folded into `associations` by the input resolver.
     */
    private function buildCriteria(LoaderInputs $inputs): Criteria
    {
        $criteria = new Criteria();

        foreach ($inputs->stringList('associations') as $association) {
            $criteria->addAssociation($association);
        }

        return $criteria;
    }
}
