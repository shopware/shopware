<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class ErrorPageController extends StorefrontController
{
    /**
     * @var ErrorTemplateResolver
     */
    protected $errorTemplateResolver;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var PageLoaderInterface
     */
    private $homePageLoader;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    public function __construct(
        ErrorTemplateResolver $errorTemplateResolver,
        TranslatorInterface $translator,
        PageLoaderInterface $homePageLoader,
        FlashBagInterface $flashBag
    ) {
        $this->errorTemplateResolver = $errorTemplateResolver;
        $this->translator = $translator;
        $this->homePageLoader = $homePageLoader;
        $this->flashBag = $flashBag;
    }

    public function error(\Exception $exception, Request $request, SalesChannelContext $context): Response
    {
        // handle error messages with flashbags
        if (!$this->flashBag->has('danger')) {
            $this->flashBag->add('danger', $this->translator->trans('error.message-default'));
        }

        try {
            //default case - show the contents of the homepage + an error message
            $homePage = $this->homePageLoader->load($request, $context);
            $response = $this->renderStorefront('@Storefront/page/home/index.html.twig', ['page' => $homePage]);
        } catch (\Exception $exception) {
            //fallback to an plain error template + an error message
            $response = $this->renderStorefront('@Storefront/page/error/index.html.twig');
        }

        $code = 500;

        if ($exception instanceof HttpException) {
            $code = $exception->getStatusCode();
        }

        $response->setStatusCode($code);

        return $response;
    }
}
