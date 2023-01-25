<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class SystemSource implements ContextSource
{
    public string $type = 'system';
}
