<?php declare(strict_types=1);

namespace Shopware\Storefront\Twig;

use Symfony\Component\HttpFoundation\Request;
use Twig_Environment;

class ErrorTemplateResolver
{

    /** @var Twig_Environment */
    protected $twig;

    public function __construct(Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    public function resolve(\Exception $exception, Request $request): ErrorTemplateStruct
    {
        $template = '@Storefront/frontend/error/error';

        if ($request->isXmlHttpRequest()) {
            $template .= '-ajax';
        }
        $dedicatedTemplate = $template . '-' . $exception->getCode();
        if ($this->twig->getLoader()->exists($dedicatedTemplate . '.html.twig')) {
            $template = $dedicatedTemplate;
        } else {
            $template .= '-std';
        }
        $template .= '.html.twig';

        return new ErrorTemplateStruct($template, ['exception' => $exception]);

    }
}
