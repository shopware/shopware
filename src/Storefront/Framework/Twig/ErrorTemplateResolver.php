<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    public function resolve(\Throwable $exception, Request $request): ErrorTemplateStruct
    {
        $template = '@Storefront/storefront/page/error/error';

        if ($request->isXmlHttpRequest()) {
            $template .= '-ajax';
        }

        $code = $exception->getCode();

        if ($exception instanceof HttpException) {
            $code = $exception->getStatusCode();
        }

        $dedicatedTemplate = $template . '-' . $code;

        if ($this->twig->getLoader()->exists($dedicatedTemplate . '.html.twig')) {
            $template = $dedicatedTemplate;
        } else {
            $template .= '-std';
        }

        $template .= '.html.twig';

        $result = new ErrorTemplateStruct($template, ['exception' => $exception]);

        return $result;
    }
}
