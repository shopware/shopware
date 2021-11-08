<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader as CoreAvailableCombinationLoader;
use Shopware\Core\Framework\Context;

/**
 * @internal Class will be removed, use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader instead
 *
 * @deprecated tag:v6.5.0 - Class will be removed
 */
class AvailableCombinationLoader extends CoreAvailableCombinationLoader
{
    private CoreAvailableCombinationLoader $loader;

    public function __construct(CoreAvailableCombinationLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * @depretacted tag:v6.5.0
     * Parameter $salesChannelId will be mandatory in future implementation
     */
    public function load(string $productId, Context $context/*, string $salesChannelId*/): AvailableCombinationResult
    {
        if (\func_num_args() === 3) {
            $salesChannelId = func_get_arg(2);

            if (\gettype($salesChannelId) !== 'string') {
                throw new \InvalidArgumentException('Argument 3 $salesChannelId must be of type string.');
            }

            $result = $this->loader->load($productId, $context, $salesChannelId);
        } else {
            $result = $this->loader->load($productId, $context);
        }

        return AvailableCombinationResult::createFrom($result);
    }
}
