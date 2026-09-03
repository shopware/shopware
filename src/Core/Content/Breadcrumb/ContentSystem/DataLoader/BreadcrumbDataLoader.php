<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use Shopware\Core\Content\Breadcrumb\SalesChannel\AbstractBreadcrumbRoute;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
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
 * @extends AbstractContentDataLoader<BreadcrumbCollection>
 */
#[Package('inventory')]
class BreadcrumbDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'breadcrumb';

    public function __construct(
        private readonly AbstractBreadcrumbRoute $breadcrumbRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'entityId'),
            new ConfigKeySpecification('type', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: 'product'),
            new ConfigKeySpecification('referrerCategoryProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: null),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $entityId = $inputs->stringOrNull('property');

        if ($entityId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $entityId = u($entityId)->lower()->toString();

        // An unsubstituted placeholder such as "{{productId}}" passes LoaderInputResolver::dereference()
        // untouched; guard after the lowercase (Uuid::VALID_PATTERN is lowercase-only) instead of reaching
        // Uuid::fromHexToBytes() in the DAL id lookups behind CategoryBreadcrumbBuilder. BreadcrumbRoute
        // declares the same domain on the HTTP side, requirements: ['id' => '[0-9a-f]{32}'].
        if (!Uuid::isValid($entityId)) {
            return ContentDataLoaderResult::notFound();
        }

        $clonedRequest = clone $request;
        $clonedRequest->attributes->set('id', $entityId);
        $clonedRequest->query->set('type', $inputs->string('type'));

        $referrerCategoryId = $inputs->stringOrNull('referrerCategoryProperty');

        // The referrer is optional (its key declares default: null): unconfigured, the query parameter stays
        // unset, which is BreadcrumbRoute's own default. A resolved referrer is an entity id like any other
        // and carries the same guard.
        if ($referrerCategoryId !== null) {
            $referrerCategoryId = u($referrerCategoryId)->lower()->toString();

            if (!Uuid::isValid($referrerCategoryId)) {
                return ContentDataLoaderResult::notFound();
            }

            $clonedRequest->query->set('referrerCategoryId', $referrerCategoryId);
        }

        // Any ShopwareHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // Known local throws: BreadcrumbRoute reaches CategoryBreadcrumbBuilder, which throws
        // BreadcrumbException::categoryNotFoundForProduct() and BreadcrumbException::productNotFound()
        // (a ProductNotFoundException the route catches only on the product branch).
        try {
            $response = $this->breadcrumbRoute->load($clonedRequest, $context);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cachedExternally($response->getBreadcrumbCollection());
    }
}
