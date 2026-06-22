<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched by ScriptResponseFactoryFacade::render() to delegate Twig-view
 * rendering to a Storefront-side listener. Listeners are expected to fill
 * `$response` with the rendered HTTP response; if `$response` remains null
 * after dispatch the facade throws `storefrontBundleMissingForHookMethod`.
 *
 * @internal
 */
#[Package('framework')]
final class RenderStorefrontForScriptEvent extends Event
{
    public ?Response $response = null;

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public readonly string $view,
        public readonly array $parameters,
        public readonly ?SalesChannelContext $salesChannelContext,
    ) {
    }
}
