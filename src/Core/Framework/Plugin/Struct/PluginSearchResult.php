<?php declare(strict_types=1);

namespace Shopware\Framework\Plugin\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Framework\Plugin\Collection\PluginBasicCollection;

class PluginSearchResult extends PluginBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
