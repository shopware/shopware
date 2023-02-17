<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Router;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('core')]
class RouterFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function factory(Hook $hook, Script $script): RouterService
    {
        return new RouterService(
            $this->requestStack,
            $this->urlGenerator,
            $hook
        );
    }

    public function getName(): string
    {
        return 'router';
    }
}
