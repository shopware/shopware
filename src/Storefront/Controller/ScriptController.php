<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Script\Api\ScriptResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Framework\Script\Api\StorefrontHook;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"storefront"})
 */
class ScriptController extends StorefrontController
{
    private GenericPageLoaderInterface $pageLoader;

    public function __construct(GenericPageLoaderInterface $pageLoader)
    {
        $this->pageLoader = $pageLoader;
    }

    /**
     * @Since("6.4.9.0")
     * @HttpCache()
     * @Route("/storefront/script/{hook}", name="frontend.script_endpoint", defaults={"XmlHttpRequest"=true}, methods={"GET", "POST"})
     */
    public function execute(string $hook, Request $request, SalesChannelContext $context): Response
    {
        //  blog/update =>  blog-update
        $hook = \str_replace('/', '-', $hook);

        $response = new ScriptResponse();
        $page = $this->pageLoader->load($request, $context);

        $hook = new StorefrontHook($hook, $request->request->all(), $request->query->all(), $response, $page, $context);

        // hook: storefront-{hook}
        $this->hook($hook);

        $response = $hook->getResponse();
        if ($response instanceof StorefrontResponse) {
            return $response;
        }

        return new JsonResponse(
            $response->body->all(),
            $response->code
        );
    }

    public function renderStorefront(string $view, array $parameters = []): Response
    {
        return parent::renderStorefront($view, $parameters);
    }
}
