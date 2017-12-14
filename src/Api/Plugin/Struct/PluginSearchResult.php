<?php declare(strict_types=1);

namespace Shopware\Api\Plugin\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Plugin\Collection\PluginBasicCollection;

class PluginSearchResult extends PluginBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
