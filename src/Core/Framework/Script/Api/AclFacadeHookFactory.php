<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\AppContextCreator;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
#[Package('framework')]
class AclFacadeHookFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(private readonly AppContextCreator $appContextCreator)
    {
    }

    public function factory(Hook $hook, Script $script): AclFacade
    {
        return new AclFacade(
            $this->appContextCreator->getAppContext($hook, $script)
        );
    }

    public function getName(): string
    {
        return 'acl';
    }
}
