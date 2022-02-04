<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Script\Api\ScriptResponseEncoder;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Framework\Script\Api\StorefrontHook;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
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

    private ScriptResponseEncoder $scriptResponseEncoder;

    public function __construct(GenericPageLoaderInterface $pageLoader, ScriptResponseEncoder $scriptResponseEncoder)
    {
        $this->pageLoader = $pageLoader;
        $this->scriptResponseEncoder = $scriptResponseEncoder;
    }

    /**
     * @Since("6.4.9.0")
     * @HttpCache()
     * @Route("/storefront/script/{hook}", name="frontend.script_endpoint", defaults={"XmlHttpRequest"=true}, methods={"GET", "POST"})
     */
    public function execute(string $hook, Request $request, SalesChannelContext $context): Response
    {
        //  blog/update =>  blog-update
        $hookName = \str_replace('/', '-', $hook);

        $page = $this->pageLoader->load($request, $context);

        $hook = new StorefrontHook($hookName, $request->request->all(), $request->query->all(), $page, $context);

        // hook: storefront-{hook}
        $this->hook($hook);

        $fields = new ResponseFields(
            $request->get('includes', [])
        );

        return $this->scriptResponseEncoder->encodeToSymfonyResponse(
            $hook->getScriptResponse(),
            $fields,
            \str_replace('-', '_', 'storefront_' . $hookName . '_response')
        );
    }

    public function renderStorefront(string $view, array $parameters = []): Response
    {
        return parent::renderStorefront($view, $parameters);
    }
}
