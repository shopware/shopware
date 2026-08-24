<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * The config serializer of {@see TestMultiReferenceGatingLoader}, tagged `content_system.config_serializer` in
 * services_test.php.
 *
 * @final
 */
#[Package('framework')]
class TestMultiReferenceGatingLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return TestMultiReferenceGatingLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        return new TestMultiReferenceGatingLoaderConfig(
            $this->requireString($data, 'entity'),
            $this->requireString($data, 'property'),
            $this->requireString($data, 'secondProperty'),
            $this->optionalString($data, 'activeProperty'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof TestMultiReferenceGatingLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', TestMultiReferenceGatingLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $key): string
    {
        if (!\array_key_exists($key, $data) || !\is_string($data[$key]) || $data[$key] === '') {
            throw ContentSystemException::invalidFieldValueType($key, 'non-empty string', \gettype($data[$key] ?? null));
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value !== null && !\is_string($value)) {
            throw ContentSystemException::invalidFieldValueType($key, 'string', \gettype($value));
        }

        return $value;
    }
}
