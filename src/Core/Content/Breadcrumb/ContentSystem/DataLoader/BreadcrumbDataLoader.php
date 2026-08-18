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

        $clonedRequest = clone $request;
        $clonedRequest->attributes->set('id', $entityId);
        $clonedRequest->query->set('type', $inputs->string('type'));

        $referrerCategoryId = $inputs->stringOrNull('referrerCategoryProperty');

        if ($referrerCategoryId !== null) {
            $clonedRequest->query->set('referrerCategoryId', u($referrerCategoryId)->lower()->toString());
        }

        $response = $this->breadcrumbRoute->load($clonedRequest, $context);

        return ContentDataLoaderResult::cachedExternally($response->getBreadcrumbCollection());
    }
}
