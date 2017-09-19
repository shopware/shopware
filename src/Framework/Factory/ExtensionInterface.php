<?php

namespace Shopware\Framework\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

interface ExtensionInterface
{
    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void;

    public function getBasicFields(): array;

    public function getDetailFields(): array;
}