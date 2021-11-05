<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Hook;

/**
 * @internal only for use by the app-system
 */
interface HookAwareInterface
{
    public function getServiceName(): string;
}
