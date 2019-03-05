<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

use Shopware\Core\Framework\Exception\StringTemplateRenderingException;

class StringTemplateRenderer
{
    /**
     * @var \Twig_Environment
     */
    private $twig;

    public function __construct()
    {
        // use private twig instance here, because we use custom template loader
        $this->twig = new \Twig_Environment(new \Twig_Loader_Array());
        $this->twig->setCache(false);
        $this->twig->enableStrictVariables();
    }

    /**
     * @throws StringTemplateRenderingException
     */
    public function render(string $templateSource, array $data): string
    {
        $name = md5($templateSource);
        $this->twig->setLoader(new \Twig_Loader_Array([$name => $templateSource]));

        try {
            return $this->twig->render($name, $data);
        } catch (\Twig_Error $error) {
            throw new StringTemplateRenderingException($error->getMessage());
        }
    }
}
