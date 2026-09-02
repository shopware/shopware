<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewRoute;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
#[Package('after-sales')]
class ProductReviewDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'product_review';

    public function __construct(
        private readonly AbstractProductReviewRoute $productReviewRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'productId'),
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
        $productId = $inputs->stringOrNull('property');

        if ($productId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $productId = u($productId)->lower()->toString();

        // A PropertyReference value passes LoaderInputResolver::dereference()'s string type check untouched, so
        // an unsubstituted template placeholder (e.g. "{{productId}}" left literal on a layout not rooted on a
        // product) reaches here as-is. Anything but an id therefore degrades rather than reaching
        // Uuid::fromHexToBytes() when the review route's `product.id` equals filter is parsed
        // (src/Core/Framework/DataAbstractionLayer/Search/Parser/SqlQueryParser.php:302). The guard runs after
        // the lowercase because Uuid::VALID_PATTERN is lowercase-only and would reject an uppercase id.
        if (!Uuid::isValid($productId)) {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = $this->buildCriteria($inputs);

        // A failure Shopware modelled as an HTTP outcome degrades the element; anything beneath that line,
        // such as a \TypeError, an \AssertionError, or a database driver failure, propagates. Catch the
        // covering ancestor rather than an enumerated set: the reachable set is open, and a decorator can
        // rewrap a named class into an unnamed one. ProductReviewRoute itself throws
        // ProductException::reviewNotActive() when the sales channel has reviews switched off
        // (src/Core/Content/Product/SalesChannel/Review/ProductReviewRoute.php:59), and the deprecated
        // ReviewNotActiveExeption extends ShopwareHttpException directly rather than through ProductException,
        // so both branches of that inheritance line are covered by the single ancestor.
        try {
            $response = $this->productReviewRoute->load($productId, $request, $context, $criteria);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cachedExternally($response->getResult());
    }

    private function buildCriteria(LoaderInputs $inputs): Criteria
    {
        $criteria = new Criteria();

        foreach ($inputs->stringList('associations') as $association) {
            $criteria->addAssociation($association);
        }

        return $criteria;
    }
}
