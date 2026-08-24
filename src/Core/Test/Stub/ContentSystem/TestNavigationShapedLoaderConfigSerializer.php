<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * The config serializer of {@see TestNavigationShapedLoader}, tagged `content_system.config_serializer` in
 * services_test.php.
 *
 * @final
 */
#[Package('framework')]
class TestNavigationShapedLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return TestNavigationShapedLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        if (!\array_key_exists('entity', $data) || !\is_string($data['entity']) || $data['entity'] === '') {
            throw ContentSystemException::invalidFieldValueType('entity', 'non-empty string', \gettype($data['entity'] ?? null));
        }

        $activeProperty = $data['activeProperty'] ?? null;
        if ($activeProperty !== null && !\is_string($activeProperty)) {
            throw ContentSystemException::invalidFieldValueType('activeProperty', 'string', \gettype($activeProperty));
        }

        return new TestNavigationShapedLoaderConfig($data['entity'], $activeProperty);
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof TestNavigationShapedLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', TestNavigationShapedLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
