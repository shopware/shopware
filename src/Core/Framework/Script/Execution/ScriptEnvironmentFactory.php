<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Twig\Extension\DebugExtension;
use Twig\Extension\ExtensionInterface;

/**
 * @internal
 */
#[Package('framework')]
class ScriptEnvironmentFactory
{
    /**
     * @param iterable<ExtensionInterface> $twigExtensions
     *
     * @internal
     */
    public function __construct(
        private readonly DebugExtension $debugExtension,
        private readonly iterable $twigExtensions,
        private readonly string $shopwareVersion,
    ) {
    }

    public function initEnv(Script $script): TwigEnvironment
    {
        $twig = new TwigEnvironment(
            new ScriptTwigLoader($script),
            $script->getTwigOptions()
        );

        foreach ($this->twigExtensions as $twigExtension) {
            $twig->addExtension($twigExtension);
        }

        if ($script->getTwigOptions()['debug'] ?? false) {
            $twig->addExtension($this->debugExtension);
        }

        $twig->addGlobal('shopware', new ArrayStruct([
            'version' => $this->shopwareVersion,
        ]));

        return $twig;
    }
}
