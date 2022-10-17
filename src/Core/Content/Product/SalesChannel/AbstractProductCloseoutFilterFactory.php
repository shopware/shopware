<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

abstract class AbstractProductCloseoutFilterFactory
{
    abstract public function getDecorated(): AbstractProductCloseoutFilterFactory;

    abstract public function create(): MultiFilter;
}
