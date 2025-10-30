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
            new TwigFunction('vite_dev_server_port', $this->getViteDevServerPort(...)),
        ];
    }

    public function isViteDevServerEnabled(): bool
    {
        // Check for flag file created by composer storefront:dev
        $flagFile = $this->projectDir . '/var/vite-dev-server.flag';

        return file_exists($flagFile);
    }

    public function getViteDevServerPort(): int
    {
        // Get port from environment variable, default to 5175 (Vite default is 5173)
        return (int) ($_ENV['STOREFRONT_VITE_PORT'] ?? $_SERVER['STOREFRONT_VITE_PORT'] ?? 5175);
    }
}
