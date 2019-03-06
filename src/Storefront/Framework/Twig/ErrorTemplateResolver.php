<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class ErrorTemplateResolver
{
    /**
     * @var Environment
     */
    protected $twig;

    public function __construct(Environment $twig)
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
