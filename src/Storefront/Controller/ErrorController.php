<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
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
     * @var HeaderPageletLoader
     */
    private $headerPageletLoader;

    public function __construct(
        ErrorTemplateResolver $errorTemplateResolver,
        FlashBagInterface $flashBag,
        HeaderPageletLoader $headerPageletLoader
    ) {
        $this->errorTemplateResolver = $errorTemplateResolver;
        $this->flashBag = $flashBag;
        $this->headerPageletLoader = $headerPageletLoader;
    }

    public function error(\Exception $exception, Request $request, SalesChannelContext $context): Response
    {
        try {
            if (!$this->flashBag->has('danger')) {
                $this->flashBag->add('danger', $this->trans('error.message-default'));
            }

            $errorTemplate = $this->errorTemplateResolver->resolve($exception, $request);

            if (!$request->isXmlHttpRequest()) {
                $header = $this->headerPageletLoader->load($request, $context);
                $errorTemplate->setHeader($header);
            }

            $response = $this->renderStorefront($errorTemplate->getTemplateName(), ['page' => $errorTemplate]);

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

        // after this controllers contents are rendered (even if the flashbag was not used e.g. 404 page)
        // clear the existing flashbag messages
        $this->flashBag->clear();

        return $response;
    }
}
