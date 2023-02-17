<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
trait HookNameTrait
{
    private readonly string $script;

    public function getName(): string
    {
        return \str_replace(
            ['{hook}', '/'],
            [$this->script, '-'],
            self::HOOK_NAME
        );
    }
}
