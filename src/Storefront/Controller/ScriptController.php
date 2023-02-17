<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\ScriptResponseEncoder;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\CacheStore;
use Shopware\Storefront\Framework\Script\Api\StorefrontHook;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @internal
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
        $page = $this->pageLoader->load($request, $context);

        $hook = new StorefrontHook($hook, $request->request->all(), $request->query->all(), $page, $context);

        $this->hook($hook);

        $symfonyResponse = $this->scriptResponseEncoder->encodeByHook(
            $hook,
            $request->get('includes', [])
        );

        $response = $hook->getScriptResponse();
        if ($response->getCache()->isEnabled()) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, ['maxAge' => $response->getCache()->getMaxAge(), 'states' => $response->getCache()->getInvalidationStates()]);
            $symfonyResponse->headers->set(CacheStore::TAG_HEADER, \json_encode($response->getCache()->getCacheTags(), \JSON_THROW_ON_ERROR));
        }

        return $symfonyResponse;
    }

    public function renderStorefront(string $view, array $parameters = []): Response
    {
        return parent::renderStorefront($view, $parameters);
    }
}
