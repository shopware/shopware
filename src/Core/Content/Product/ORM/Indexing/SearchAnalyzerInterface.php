<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ORM\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;

interface SearchAnalyzerInterface
{
    public function analyze(string $definition, Entity $entity, Context $context): array;
}
