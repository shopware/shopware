<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script\Registry;

use Shopware\Core\Framework\App\Script\ExecutableScript;
use Shopware\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
interface ExecutableScriptLoaderInterface
{
    /**
     * @return array<string, ExecutableScript[]>
     */
    public function loadExecutableScripts(Context $context): array;
}
