<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig;

use Shopware\Core\Framework\Context;
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

    public function __construct(Environment $environment)
    {
        // use private twig instance here, because we use custom template loader
        $this->twig = new Environment(new ArrayLoader());
        $this->twig->setCache(false);
        $this->twig->enableStrictVariables();

        foreach ($environment->getExtensions() as $extension) {
            if ($this->twig->hasExtension(get_class($extension))) {
                continue;
            }
            $this->twig->addExtension($extension);
        }
    }

    /**
     * @throws StringTemplateRenderingException
     */
    public function render(string $templateSource, array $data, Context $context): string
    {
        $name = md5($templateSource);
        $this->twig->setLoader(new ArrayLoader([$name => $templateSource]));

        $this->twig->addGlobal('context', $context);

        try {
            return $this->twig->render($name, $data);
        } catch (Error $error) {
            throw new StringTemplateRenderingException($error->getMessage());
        }
    }

    public function enableTestMode(): void
    {
        $this->twig->disableStrictVariables();
    }

    public function disableTestMode(): void
    {
        $this->twig->enableStrictVariables();
    }
}
