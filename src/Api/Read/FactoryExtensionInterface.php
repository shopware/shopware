<?php declare(strict_types=1);

namespace Shopware\Api\Read;

use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;

interface FactoryExtensionInterface
{
    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void;

    public function getBasicFields(): array;

    public function getDetailFields(): array;
}
