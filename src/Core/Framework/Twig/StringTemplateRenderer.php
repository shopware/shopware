<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

use Shopware\Core\Framework\Twig\Exception\StringTemplateRenderingException;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;

class StringTemplateRenderer
{
    /**
     * @var Environment
     */
    private $twig;

    public function __construct()
    {
        // use private twig instance here, because we use custom template loader
        $this->twig = new Environment(new ArrayLoader());
        $this->twig->setCache(false);
        $this->twig->enableStrictVariables();
    }

    /**
     * @throws StringTemplateRenderingException
     */
    public function render(string $templateSource, array $data): string
    {
        $name = md5($templateSource);
        $this->twig->setLoader(new ArrayLoader([$name => $templateSource]));

        try {
            return $this->twig->render($name, $data);
        } catch (Error $error) {
            throw new StringTemplateRenderingException($error->getMessage());
        }
    }
}
