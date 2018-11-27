<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ErrorController extends StorefrontController
{
    /**
     * @param \Exception $exception
     * @param Request $request
     * @return Response
     */
    public function error(\Exception $exception, Request $request): Response
    {
        try {
            $template = $this->resolveTemplate($exception, $request);
            return $this->renderStorefront($template, ['exception' => $exception]);
        } catch (\Exception $e) {
            return $this->renderStorefront(
                '@Storefront/frontend/error/exception.html.twig',
                ['exception' => $exception, 'followingException' => $e]
            );
        }
    }

    /**
     * @param \Exception $exception
     * @param Request $request
     * @return string
     */
    private function resolveTemplate(\Exception $exception, Request $request): string
    {
        $template = '@Storefront/frontend/error/error';

        if ($request->isXmlHttpRequest()) {
            $template .= '-ajax';
        }
        $dedicatedTemplate = $template . '-' . $exception->getCode();
        if ($this->container->get('twig')->getLoader()->exists($dedicatedTemplate . '.html.twig')) {
            $template = $dedicatedTemplate;
        } else {
            $template .= '-std';
        }
        $template .= '.html.twig';

        return $template;
    }
}
