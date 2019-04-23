<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    public function __construct(ErrorTemplateResolver $errorTemplateResolver, TranslatorInterface $translator)
    {
        $this->errorTemplateResolver = $errorTemplateResolver;
        $this->translator = $translator;
    }

    public function error(\Exception $exception, Request $request): Response
    {
        $response = $this->forward("Shopware\Storefront\PageController\HomePageController:index");

        $code = 500;

        if ($exception instanceof HttpException) {
            $code = $exception->getStatusCode();
        }

        $response->setStatusCode($code);

        return $response;
    }

    /**
     * @Route(name="frontend.error.message", path="error", options={"seo"="false"}, methods={"GET"})
     */
    public function message(Request $request, SalesChannelContext $context): Response
    {
        $snippet = $request->get('snippet', 'error.message-default');

        $message = $request->get('message', null);

        if ($message === null) {
            $message = $this->translator->trans($snippet);
        }

        return $this->renderStorefront('@Storefront/page/error/message.html.twig', ['errorMessage' => $message]);
    }
}
