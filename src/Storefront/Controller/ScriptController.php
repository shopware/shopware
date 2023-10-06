<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\ScriptResponseEncoder;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Storefront\Framework\Script\Api\StorefrontHook;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('core')]
class ScriptController extends StorefrontController
{
    public function __construct(
        private readonly GenericPageLoaderInterface $pageLoader,
        private readonly ScriptResponseEncoder $scriptResponseEncoder
    ) {
    }

    #[Route(path: '/storefront/script/{hook}', name: 'frontend.script_endpoint', requirements: ['hook' => '.+'], defaults: ['XmlHttpRequest' => true], methods: ['GET', 'POST'])]
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

        $response = $hook->getScriptResponse();

        $symfonyResponse = $this->scriptResponseEncoder->encodeToSymfonyResponse(
            $response,
            $fields,
            \str_replace('-', '_', 'storefront_' . $hookName . '_response')
        );

        if ($response->getCache()->isEnabled()) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, ['maxAge' => $response->getCache()->getMaxAge(), 'states' => $response->getCache()->getInvalidationStates()]);
            $symfonyResponse->headers->set(CacheStore::TAG_HEADER, \json_encode($response->getCache()->getCacheTags(), \JSON_THROW_ON_ERROR));
        }

        return $symfonyResponse;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function renderStorefrontForScript(string $view, array $parameters = []): Response
    {
        return $this->renderStorefront($view, $parameters);
    }
}
