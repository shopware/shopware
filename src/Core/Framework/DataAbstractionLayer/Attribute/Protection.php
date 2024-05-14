<?php

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Context;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Protection
{
    final public const SYSTEM_SCOPE = Context::SYSTEM_SCOPE;
    final public const USER_SCOPE = Context::USER_SCOPE;
    final public const CRUD_API_SCOPE = Context::CRUD_API_SCOPE;

    public function __construct(public array $write) {}
}
