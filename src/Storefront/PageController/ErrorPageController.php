<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Exception;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ErrorPageController extends StorefrontController
{
    /**
     * @var ErrorTemplateResolver
     */
    protected $errorTemplateResolver;

    public function __construct(ErrorTemplateResolver $errorTemplateResolver)
    {
        $this->errorTemplateResolver = $errorTemplateResolver;
    }

    public function error(Exception $exception, Request $request): Response
    {
        try {
            $errorTemplate = $this->errorTemplateResolver->resolve($exception, $request);

            return $this->renderStorefront($errorTemplate->getTemplateName(), $errorTemplate->getArguments());
        } catch (Exception $e) { //final Fallback
            return $this->renderStorefront(
                '@Storefront/index/error.html.twig',
                ['exception' => $exception, 'followingException' => $e]
            );
        }
    }
}
