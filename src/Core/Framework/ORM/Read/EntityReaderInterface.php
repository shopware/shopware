<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Read;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Application\Context\Struct\ApplicationContext;

interface EntityReaderInterface
{
    public function readDetail(string $definition, array $ids, ApplicationContext $context): EntityCollection;

    public function readBasic(string $definition, array $ids, ApplicationContext $context): EntityCollection;

    public function readRaw(string $definition, array $ids, ApplicationContext $context): EntityCollection;
}
