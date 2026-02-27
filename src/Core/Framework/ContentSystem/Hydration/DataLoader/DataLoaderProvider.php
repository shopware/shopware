<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class DataLoaderProvider
{
    /**
     * @param ServiceLocator<AbstractContentDataLoader> $locator
     */
    public function __construct(
        private readonly ServiceLocator $locator
    ) {
    }

    /**
     * @throws ContentSystemException
     */
    public function get(string $type): AbstractContentDataLoader
    {
        if (!$this->locator->has($type)) {
            throw ContentSystemException::dataLoaderNotRegistered($type, 'unknown', 'unknown');
        }

        return $this->locator->get($type);
    }
}
