<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Common\Stubs\SystemConfigService;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class ConfigService extends SystemConfigService
{
    /**
     * @var array<string, string|null>
     */
    private $config;

    /**
     * @param array<string, string|null> $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function get(string $key, ?string $salesChannelId = null)
    {
        return $this->config[$key];
    }
}
