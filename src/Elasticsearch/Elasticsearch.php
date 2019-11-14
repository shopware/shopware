<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use Shopware\Core\Framework\Bundle;
use Shopware\Elasticsearch\DependencyInjection\ElasticsearchExtension;

class Elasticsearch extends Bundle
{
    protected $name = 'Elasticsearch';

    public function createContainerExtension()
    {
        return new ElasticsearchExtension();
    }
}
