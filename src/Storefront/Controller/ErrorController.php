<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
use Shopware\Storefront\Page\Navigation\Error\ErrorPageLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ErrorController extends StorefrontController
{
    /**
     * @var ErrorTemplateResolver
     */
    protected $errorTemplateResolver;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var HeaderPageletLoaderInterface
     */
    private $headerPageletLoader;

    /**
     * @var ErrorPageLoaderInterface
     */
    private $errorPageLoader;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        ErrorTemplateResolver $errorTemplateResolver,
        FlashBagInterface $flashBag,
        HeaderPageletLoaderInterface $headerPageletLoader,
        SystemConfigService $systemConfigService,
        ErrorPageLoaderInterface $errorPageLoader
    ) {
        $this->errorTemplateResolver = $errorTemplateResolver;
        $this->flashBag = $flashBag;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->errorPageLoader = $errorPageLoader;
        $this->systemConfigService = $systemConfigService;
    }

    public function error(\Throwable $exception, Request $request, SalesChannelContext $context): Response
    {
        try {
            $is404StatusCode = $exception instanceof HttpException
                && $exception->getStatusCode() === Response::HTTP_NOT_FOUND;

            if (!$is404StatusCode && !$this->flashBag->has('danger')) {
                $this->flashBag->add('danger', $this->trans('error.message-default'));
            }

            $request->attributes->set('navigationId', $context->getSalesChannel()->getNavigationCategoryId());

            $salesChannelId = $context->getSalesChannel()->getId();
            $cmsErrorLayoutId = $this->systemConfigService->getString('core.basicInformation.404Page', $salesChannelId);
            if ($cmsErrorLayoutId !== '' && $is404StatusCode) {
                $errorPage = $this->errorPageLoader->load($cmsErrorLayoutId, $request, $context);

                $response = $this->renderStorefront(
                    '@Storefront/storefront/page/content/index.html.twig',
                    ['page' => $errorPage]
                );
            } else {
                $errorTemplate = $this->errorTemplateResolver->resolve($exception, $request);

                if (!$request->isXmlHttpRequest()) {
                    $header = $this->headerPageletLoader->load($request, $context);
                    $errorTemplate->setHeader($header);
                }

                $response = $this->renderStorefront($errorTemplate->getTemplateName(), ['page' => $errorTemplate]);
            }

            if ($exception instanceof HttpException) {
                $response->setStatusCode($exception->getStatusCode());
            }
        } catch (\Exception $e) { //final Fallback
            $response = $this->renderStorefront(
                '@Storefront/storefront/page/error/index.html.twig',
                ['exception' => $exception, 'followingException' => $e]
            );

            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // After this controllers content is rendered (even if the flashbag was not used e.g. on a 404 page),
        // clear the existing flashbag messages
        $this->flashBag->clear();

        return $response;
    }
}
