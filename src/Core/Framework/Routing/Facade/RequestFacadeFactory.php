<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Facade;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('core')]
class RequestFacadeFactory extends HookServiceFactory
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function factory(Hook $hook, Script $script): RequestFacade
    {
        $request = $this->requestStack->getMainRequest();
        \assert($request !== null);

        return new RequestFacade($request);
    }

    public function getName(): string
    {
        return 'request';
    }
}
