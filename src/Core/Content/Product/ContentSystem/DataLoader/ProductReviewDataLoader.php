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

        // An unsubstituted placeholder such as "{{productId}}" passes LoaderInputResolver::dereference()
        // untouched; guard after the lowercase (Uuid::VALID_PATTERN is lowercase-only) instead of reaching
        // Uuid::fromHexToBytes() when SqlQueryParser parses the review route's `product.id` equals filter.
        if (!Uuid::isValid($productId)) {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = $this->buildCriteria($inputs);

        // Any ShopwareHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // Known local throws: ProductReviewRoute throws ProductException::reviewNotActive() when the sales
        // channel has reviews switched off; the deprecated ReviewNotActiveExeption extends
        // ShopwareHttpException directly, so both inheritance lines are covered.
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
