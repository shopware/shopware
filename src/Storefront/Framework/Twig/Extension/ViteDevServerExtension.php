<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('framework')]
class ViteDevServerExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('vite_dev_server_enabled', $this->isViteDevServerEnabled(...)),
        ];
    }

    public function isViteDevServerEnabled(): bool
    {
        // Check for flag file created by composer storefront:dev
        $flagFile = $this->projectDir . '/var/vite-dev-server.flag';

        return file_exists($flagFile);
    }
}
