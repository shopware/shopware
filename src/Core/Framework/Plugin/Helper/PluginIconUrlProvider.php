<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Helper;

use Shopware\Core\Framework\Util\AssetService;

class PluginIconUrlProvider
{
    /**
     * @var AssetService
     */
    private $assetService;
    /**
     * @var string
     */
    private $baseUrl;

    public function __construct(AssetService $assetService, string $baseUrl)
    {
        $this->assetService = $assetService;
        $this->baseUrl = $baseUrl;
    }

    public function getPluginIconUrl(string $pluginName): ?string
    {
        if (!$this->assetService->hasPluginIcon($pluginName)) {
            return null;
        }

        return $this->baseUrl . '/bundles/' . $pluginName . '/plugin.png';
    }
}
