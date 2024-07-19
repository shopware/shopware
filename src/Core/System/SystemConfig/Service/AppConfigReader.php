<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Service;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

/**
 * @internal
 */
#[Package('system-settings')]
class AppConfigReader
{
    public function __construct(private readonly SourceResolver $sourceResolver, private readonly ConfigReader $configReader)
    {
    }

    /**
     * @return array<array<string, mixed>>|null
     */
    public function read(AppEntity $app): ?array
    {
        $fs = $this->sourceResolver->filesystemForApp($app);
        if (!$fs->has('Resources/config/config.xml')) {
            return null;
        }

        return $this->configReader->read($fs->path('Resources/config/config.xml'));
    }
}
