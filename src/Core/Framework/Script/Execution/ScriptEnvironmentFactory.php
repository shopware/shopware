<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\DebugExtension;
use Twig\Extension\ExtensionInterface;

/**
 * @internal
 */
#[Package('framework')]
class ScriptEnvironmentFactory implements ResetInterface
{
    /**
     * @var array<string, TwigEnvironment>
     */
    private array $twigEnvs = [];

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
        $scriptHash = Hasher::hash($script->getName() . $script->getScript());

        if (isset($this->twigEnvs[$scriptHash])) {
            return $this->twigEnvs[$scriptHash];
        }

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

        return $this->twigEnvs[$scriptHash] = $twig;
    }

    public function reset(): void
    {
        $this->twigEnvs = [];
    }
}
