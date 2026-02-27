<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Abstract base for loading data for content elements.
 *
 * Implementations fetch data based on DataRequirement configuration
 * and return results with cache tag information.
 *
 * Return values:
 * - ContentDataLoaderResult::notFound() → No data, page cacheable
 * - ContentDataLoaderResult::cached($data, ...$tags) → Data with cache tags
 * - ContentDataLoaderResult::cachedExternally($data) → Data, tags handled elsewhere
 */
#[Package('framework')]
abstract class AbstractContentDataLoader
{
    abstract public function getDecorated(): AbstractContentDataLoader;

    /**
     * Returns the requirement type identifier for DI service location.
     * This method is used by the ServiceLocator for indexing.
     */
    abstract public static function getRequirementType(): string;

    abstract public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult;
}
