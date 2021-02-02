<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Context;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

class StringTemplateRenderer
{
    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var Environment
     */
    private $platformTwig;

    public function __construct(Environment $environment)
    {
        $this->platformTwig = $environment;
        $this->initialize();
    }

    public function initialize(): void
    {
        // use private twig instance here, because we use custom template loader
        $this->twig = new Environment(new ArrayLoader());
        $this->twig->setCache(false);
        $this->disableTestMode();
        foreach ($this->platformTwig->getExtensions() as $extension) {
            if ($this->twig->hasExtension(\get_class($extension))) {
                continue;
            }
            $this->twig->addExtension($extension);
        }
        if ($this->twig->hasExtension(CoreExtension::class) && $this->platformTwig->hasExtension(CoreExtension::class)) {
            /** @var CoreExtension $coreExtensionInternal */
            $coreExtensionInternal = $this->twig->getExtension(CoreExtension::class);
            /** @var CoreExtension $coreExtensionGlobal */
            $coreExtensionGlobal = $this->platformTwig->getExtension(CoreExtension::class);

            $coreExtensionInternal->setTimezone($coreExtensionGlobal->getTimezone());
            $coreExtensionInternal->setDateFormat(...$coreExtensionGlobal->getDateFormat());
            $coreExtensionInternal->setNumberFormat(...$coreExtensionGlobal->getNumberFormat());
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
        $this->twig->addGlobal('testMode', true);
        $this->twig->disableStrictVariables();
    }

    public function disableTestMode(): void
    {
        $this->twig->addGlobal('testMode', false);
        $this->twig->enableStrictVariables();
    }
}
