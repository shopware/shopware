<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Security;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('core')]
class SecurityFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    public function factory(Hook $hook, Script $script): SecurityService
    {
        $request = $this->requestStack->getMainRequest();
        $nonce = $request?->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);

        return new SecurityService(
            $nonce
        );
    }

    public function getName(): string
    {
        return 'security';
    }
}
