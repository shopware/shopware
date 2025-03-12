<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class AclFacade
{
    public function __construct(private readonly Context $context)
    {
    }

    public function can(string $privilege): bool
    {
        return $this->context->isAllowed($privilege);
    }
}
