<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

interface ElasticsearchDefinitionInterface
{
    public function getEntityDefinition(): EntityDefinition;

    public function getMapping(Context $context): array;

    public function extendCriteria(Criteria $criteria): void;
}
