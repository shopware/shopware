<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use Shopware\Core\Content\Breadcrumb\SalesChannel\AbstractBreadcrumbRoute;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
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

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof BreadcrumbLoaderConfig) {
            return ContentDataLoaderResult::notFound(); // @phpstan-ignore return.type
        }

        $propertyName = $config->property ?? 'entityId';
        $entityId = $element->getProperty($propertyName);

        if (!\is_string($entityId)) {
            return ContentDataLoaderResult::notFound(); // @phpstan-ignore return.type
        }

        $entityId = u($entityId)->lower()->toString();

        $clonedRequest = clone $request;
        $clonedRequest->attributes->set('id', $entityId);
        $clonedRequest->query->set('type', $config->type);

        if ($config->referrerCategoryProperty !== null) {
            $referrerCategoryId = $element->getProperty($config->referrerCategoryProperty);
            if (\is_string($referrerCategoryId)) {
                $clonedRequest->query->set('referrerCategoryId', u($referrerCategoryId)->lower()->toString());
            }
        }

        $response = $this->breadcrumbRoute->load($clonedRequest, $context);

        return ContentDataLoaderResult::cachedExternally($response->getBreadcrumbCollection());
    }
}
