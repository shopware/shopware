<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('discovery')]
class DataLoaderProvider
{
    /**
     * @param ServiceLocator<ContentDataLoaderInterface> $locator
     */
    public function __construct(
        private readonly ServiceLocator $locator
    ) {
    }

    /**
     * @throws ContentSystemException
     */
    public function get(string $type): ContentDataLoaderInterface
    {
        if (!$this->locator->has($type)) {
            throw ContentSystemException::dataLoaderNotRegistered($type, 'unknown', 'unknown');
        }

        return $this->locator->get($type);
    }
}
