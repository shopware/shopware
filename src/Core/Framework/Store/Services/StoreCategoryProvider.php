<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Store\Struct\StoreCategoryCollection;

/**
 * @internal
 */
class StoreCategoryProvider extends AbstractStoreCategoryProvider
{
    /**
     * @var StoreClient
     */
    private $storeClient;

    public function __construct(StoreClient $storeClient)
    {
        $this->storeClient = $storeClient;
    }

    public function getCategories(Context $context): StoreCategoryCollection
    {
        return new StoreCategoryCollection($this->storeClient->getCategories($context));
    }

    protected function getDecorated(): AbstractStoreCategoryProvider
    {
        throw new DecorationPatternException(self::class);
    }
}
