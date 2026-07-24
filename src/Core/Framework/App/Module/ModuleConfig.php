<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Module;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * The admin modules an app declares: the module entries plus the optional main module. One per app.
 *
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
readonly class ModuleConfig implements AppFeatureConfig
{
    /**
     * @param list<Module> $modules
     */
    public function __construct(
        public array $modules,
        public ?MainModule $mainModule,
    ) {
    }

    public function getName(): string
    {
        return 'admin';
    }
}
