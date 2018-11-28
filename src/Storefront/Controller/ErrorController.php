<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Storefront\Twig\ErrorTemplateResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ErrorController extends StorefrontController
{
    /** @var ErrorTemplateResolver  */
    private $errorTemplateResolver;


    /**
     * ErrorController constructor.
     * @param ErrorTemplateResolver $errorTemplateResolver
     */
    public function __construct(ErrorTemplateResolver $errorTemplateResolver)
    {
        $this->errorTemplateResolver = $errorTemplateResolver;
    }

    /**
     * @param \Exception $exception
     * @param Request $request
     * @return Response
     */
    public function error(\Exception $exception, Request $request): Response
    {
        try {
            $errorTemplate = $this->errorTemplateResolver->resolve($exception, $request);
            return $this->renderStorefront($errorTemplate->getTemplateName(), $errorTemplate->getArguments());
        } catch (\Exception $e) { //final Fallback
            return $this->renderStorefront(
                '@Storefront/frontend/error/exception.html.twig',
                ['exception' => $exception, 'followingException' => $e]
            );
        }
    }
}
