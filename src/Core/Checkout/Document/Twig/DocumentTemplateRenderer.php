<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Twig;

use Shopware\Core\Framework\Twig\TemplateFinder;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DocumentTemplateRenderer
{
    /**
     * @var TemplateFinder
     */
    private $templateFinder;

    /**
     * @var Environment
     */
    private $twig;

    public function __construct(TemplateFinder $templateFinder, Environment $twig)
    {
        $this->templateFinder = $templateFinder;
        $this->twig = $twig;
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(string $view, array $parameters = []): string
    {
        $view = $this->resolveView($view);

        return $this->twig->render($view, $parameters);
    }

    /**
     * @throws LoaderError
     */
    private function resolveView(string $view): string
    {
        //remove static template inheritance prefix
        if (strpos($view, '@') === 0) {
            $viewParts = explode('/', $view);
            array_shift($viewParts);
            $view = implode('/', $viewParts);
        }

        return $this->templateFinder->find($view);
    }
}
