<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Twig\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\CoreExtension;
use Twig\Extension\EscaperExtension;
use Twig\Loader\ArrayLoader;

/**
 * @final
 */
#[Package('core')]
class StringTemplateRenderer
{
    private Environment $twig;

    /**
     * @internal
     */
    public function __construct(
        private readonly Environment $platformTwig,
        private readonly string $cacheDir
    ) {
        $this->initialize();
    }

    public function initialize(): void
    {
        // use private twig instance here, because we use custom template loader
        $this->twig = new TwigEnvironment(new ArrayLoader(), [
            'cache' => new FilesystemCache($this->cacheDir . '/string-template-renderer'),
        ]);

        $this->disableTestMode();
        foreach ($this->platformTwig->getExtensions() as $extension) {
            if ($this->twig->hasExtension($extension::class)) {
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
     * @param array<string, mixed> $data
     *
     * @throws StringTemplateRenderingException
     */
    public function render(string $templateSource, array $data, Context $context, bool $htmlEscape = true): string
    {
        $name = md5($templateSource . !$htmlEscape);
        $this->twig->setLoader(new ArrayLoader([$name => $templateSource]));

        $this->twig->addGlobal('context', $context);

        if ($this->twig->hasExtension(EscaperExtension::class)) {
            /** @var EscaperExtension $escaperExtension */
            $escaperExtension = $this->twig->getExtension(EscaperExtension::class);
            $escaperExtension->setDefaultStrategy($htmlEscape ? 'html' : false);
        }

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
