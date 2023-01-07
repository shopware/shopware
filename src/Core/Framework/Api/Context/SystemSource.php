<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

/**
 * @package core
 */
class SystemSource implements ContextSource
{
    public string $type = 'system';
}
