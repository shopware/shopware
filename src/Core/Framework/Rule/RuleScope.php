<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Context;

abstract class RuleScope
{
    abstract public function getContext(): Context;
}
