<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Read;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Context\Struct\ApplicationContext;

interface EntityReaderInterface
{
    public function readDetail(string $definition, array $ids, ApplicationContext $context): EntityCollection;

    public function readBasic(string $definition, array $ids, ApplicationContext $context): EntityCollection;

    public function readRaw(string $definition, array $ids, ApplicationContext $context): EntityCollection;
}
