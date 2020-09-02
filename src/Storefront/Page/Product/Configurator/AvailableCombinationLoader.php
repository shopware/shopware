<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader as CoreAvailableCombinationLoader;
use Shopware\Core\Framework\Context;

class AvailableCombinationLoader extends CoreAvailableCombinationLoader
{
    /**
     * @var CoreAvailableCombinationLoader
     */
    private $loader;

    public function __construct(CoreAvailableCombinationLoader $loader)
    {
        $this->loader = $loader;
    }

    public function load(string $productId, Context $context): AvailableCombinationResult
    {
        $result = $this->loader->load($productId, $context);

        return AvailableCombinationResult::createFrom($result);
    }
}
