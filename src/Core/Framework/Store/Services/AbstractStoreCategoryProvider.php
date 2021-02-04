<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Struct\StoreCategoryCollection;

abstract class AbstractStoreCategoryProvider
{
    abstract public function getCategories(Context $context): StoreCategoryCollection;

    abstract protected function getDecorated(): AbstractStoreCategoryProvider;
}
