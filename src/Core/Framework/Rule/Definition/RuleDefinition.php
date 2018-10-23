<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Definition;

use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;

interface RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct;
}