<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Exception\HookMethodException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\ScriptController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * The `response` service allows you to create HTTP-Responses.
 *
 * @script-service custom_endpoint
 */
#[Package('core')]
class ScriptResponseFactoryFacade
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ?ScriptController $scriptController,
        private readonly ?SalesChannelContext $salesChannelContext
    ) {
    }

    /**
     * The `json()` method allows you to create a JSON-Response.
     *
     * @param array<mixed> $data The data that should be sent in the response as array.
     * @param int $code The HTTP-Status-Code of the response, defaults to 200.
     *
     * @return ScriptResponse The created response object, remember to assign it to the hook with `hook.setResponse()`.
     *
     * @example /api-simple-script/simple-script.twig 3 Return hard coded values as JsonResponse.
     * @example /api-repository-test/api-repository-test.twig Search for products and return them in a JsonResponse.
     * @example /api-action-button/action-button-script-integration.twig Provide a response to a ActionButtons request from the administration.
     */
    public function json(array $data, int $code = Response::HTTP_OK): ScriptResponse
    {
        $response = new ScriptResponse(null, $code);
        $response->setBody($data);

        return $response;
    }

    /**
     * The `redirect()` method allows you to create a RedirectResponse.
     *
     * @param string $route The name of the route that should be redirected to.
     * @param array<mixed> $parameters The parameters needing to generate the URL of the route as an associative array.
     * @param int $code he HTTP-Status-Code of the response, defaults to 302.
     *
     * @return ScriptResponse The created response object, remember to assign it to the hook with `hook.setResponse()`.
     *
     * @example /api-redirect-response/redirect-script.twig 3 Redirect to an Admin-API route.
     * @example /storefront-redirect-response/script.twig 3 Redirect to a storefront page.
     */
    public function redirect(string $route, array $parameters, int $code = Response::HTTP_FOUND): ScriptResponse
    {
        $url = $this->router->generate($route, $parameters);

        return new ScriptResponse(
            new RedirectResponse($url, $code),
            $code
        );
    }

    /**
     * The `render()` method allows you to render a twig view with the parameters you provide and create a StorefrontResponse.
     *
     * Note that the `render()` method will throw an exception if it is called from outside a `SalesChannelContext` (e.g. from an `/api` route)
     * or if the Storefront-bundle is not installed.
     *
     * @param string $view The name of the twig template you want to render e.g. `@Storefront/storefront/page/content/detail.html.twig`
     * @param array<mixed> $parameters The parameters you want to pass to the template, ensure that you pass the `page` parameter from the hook to the templates.
     *
     * @return ScriptResponse The created response object with the rendered template as content, remember to assign it to the hook with `hook.setResponse()`.
     *
     * @example storefront-render/script.twig 3 Fetch a product, add it to the page and return a rendered response.
     */
    public function render(string $view, array $parameters = []): ScriptResponse
    {
        if ($this->scriptController === null) {
            throw HookMethodException::storefrontBundleMissing(__METHOD__);
        }

        if ($this->salesChannelContext === null) {
            throw HookMethodException::outsideOfSalesChannelContext(__METHOD__);
        }

        $inner = $this->scriptController->renderStorefrontForScript($view, $parameters);

        return new ScriptResponse($inner, $inner->getStatusCode());
    }
}
