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

        // A PropertyReference value passes LoaderInputResolver::dereference()'s string type check untouched, so
        // an unsubstituted template placeholder (e.g. "{{productId}}" left literal on a layout not rooted on a
        // product) reaches here as-is. Anything but an id therefore degrades rather than reaching
        // Uuid::fromHexToBytes() in the DAL id lookups behind CategoryBreadcrumbBuilder. BreadcrumbRoute
        // declares the same domain on the HTTP side, `requirements: ['id' => '[0-9a-f]{32}']`
        // (src/Core/Content/Breadcrumb/SalesChannel/BreadcrumbRoute.php:40). The guard runs after the lowercase
        // because Uuid::VALID_PATTERN is lowercase-only and would reject an uppercase id.
        if (!Uuid::isValid($entityId)) {
            return ContentDataLoaderResult::notFound();
        }

        $clonedRequest = clone $request;
        $clonedRequest->attributes->set('id', $entityId);
        $clonedRequest->query->set('type', $inputs->string('type'));

        $referrerCategoryId = $inputs->stringOrNull('referrerCategoryProperty');

        // The referrer is optional: its key declares `default: null`, so an unconfigured reference resolves to
        // null and the query parameter simply stays unset, which is the route's own default
        // (BreadcrumbRoute::load() reads it as ''). A resolved referrer is an entity id like any other and
        // carries the same guard: BreadcrumbRoute passes it to
        // CategoryBreadcrumbBuilder::getProductCategoryByReferrer(), which loads it by id when the product's
        // category tree contains it.
        if ($referrerCategoryId !== null) {
            $referrerCategoryId = u($referrerCategoryId)->lower()->toString();

            if (!Uuid::isValid($referrerCategoryId)) {
                return ContentDataLoaderResult::notFound();
            }

            $clonedRequest->query->set('referrerCategoryId', $referrerCategoryId);
        }

        // A failure Shopware modelled as an HTTP outcome degrades the element; anything beneath that line,
        // such as a \TypeError, an \AssertionError, or a database driver failure, propagates. Catch the
        // covering ancestor rather than an enumerated set: the reachable set is open, and a decorator can
        // rewrap a named class into an unnamed one. BreadcrumbRoute reaches CategoryBreadcrumbBuilder, which
        // throws BreadcrumbException::categoryNotFoundForProduct()
        // (src/Core/Content/Category/Service/CategoryBreadcrumbBuilder.php:56) and
        // BreadcrumbException::productNotFound() (:191, returning ProductNotFoundException, which the route
        // catches only on the product branch).
        try {
            $response = $this->breadcrumbRoute->load($clonedRequest, $context);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cachedExternally($response->getBreadcrumbCollection());
    }
}
