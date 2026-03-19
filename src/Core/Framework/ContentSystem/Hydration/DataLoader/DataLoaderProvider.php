<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DataLoaderProvider
{
    /**
     * @param ServiceLocator<AbstractContentDataLoader<Struct>> $locator
     */
    public function __construct(
        private readonly ServiceLocator $locator
    ) {
    }

    /**
     * @throws ContentSystemException
     *
     * @return AbstractContentDataLoader<Struct>
     */
    public function get(string $type): AbstractContentDataLoader
    {
        if (!$this->locator->has($type)) {
            throw ContentSystemException::dataLoaderNotRegistered($type, 'unknown', 'unknown');
        }

        return $this->locator->get($type);
    }
}
