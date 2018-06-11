<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Struct;

use Shopware\Core\Checkout\DiscountSurcharge\Collection\DiscountSurchargeBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class DiscountSurchargeSearchResult extends DiscountSurchargeBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
