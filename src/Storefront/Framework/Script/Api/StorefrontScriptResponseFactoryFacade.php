<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\ScriptResponse;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\ScriptController;
use Symfony\Component\Routing\RouterInterface;

/**
 * The `response` service variant available when the Storefront bundle is installed.
 * It adds the `render()` method on top of the core `response` service so storefront scripts can render Twig views.
 *
 * @internal
 */
#[Package('framework')]
class StorefrontScriptResponseFactoryFacade extends ScriptResponseFactoryFacade
{
    /**
     * @internal
     */
    public function __construct(
        RouterInterface $router,
        private readonly ScriptController $scriptController,
        SalesChannelContext $salesChannelContext,
    ) {
        parent::__construct($router, null, $salesChannelContext);
    }

    /**
     * The `render()` method allows you to render a twig view with the parameters you provide and create a StorefrontResponse.
     *
     * Note that the `render()` method will throw an exception if it is called from outside a `SalesChannelContext` (e.g. from an `/api` route).
     *
     * @param string $view The name of the twig template you want to render e.g. `@Storefront/storefront/page/content/detail.html.twig`
     * @param array<string, mixed> $parameters The parameters you want to pass to the template, ensure that you pass the `page` parameter from the hook to the templates.
     *
     * @return ScriptResponse The created response object with the rendered template as content, remember to assign it to the hook with `hook.setResponse()`.
     *
     * @example storefront-render/script.twig 3 Fetch a product, add it to the page and return a rendered response.
     */
    public function render(string $view, array $parameters = []): ScriptResponse
    {
        $inner = $this->scriptController->renderStorefrontForScript($view, $parameters);

        return new ScriptResponse($inner, $inner->getStatusCode());
    }
}
