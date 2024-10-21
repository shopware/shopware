<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
readonly class SymfonySystemConfigService
{
    /**
     * @param array<string, array<mixed>> $configuration
     */
    public function __construct(private array $configuration)
    {
    }

    /**
     * @return array<mixed>
     */
    public function getConfig(?string $salesChannelId = null): array
    {
        return $this->configuration[$salesChannelId ?? 'default'] ?? [];
    }

    public function get(string $configKey, ?string $salesChannelId = null): mixed
    {
        if ($salesChannelId !== null) {
            $salesChannelConfiguration = $this->configuration[$salesChannelId] ?? [];

            if (\array_key_exists($configKey, $salesChannelConfiguration)) {
                return $salesChannelConfiguration[$configKey];
            }
        }

        $defaultConfiguration = $this->configuration['default'] ?? [];

        return $defaultConfiguration[$configKey] ?? null;
    }

    public function has(string $configKey): bool
    {
        foreach ($this->configuration as $salesChannelConfiguration) {
            if (\array_key_exists($configKey, $salesChannelConfiguration)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $merged
     *
     * @return array<mixed>
     */
    public function override(array $merged, ?string $salesChannelId, bool $inherit = true, bool $nesting = true): array
    {
        $values = [
            $this->getConfig($salesChannelId),
        ];

        if ($salesChannelId !== null && $inherit) {
            array_unshift($values, $this->getConfig());
        }

        $specific = array_merge(...$values);

        if (!$nesting) {
            return array_replace_recursive($merged, $specific);
        }

        foreach ($specific as $key => $value) {
            $keys = \explode('.', (string) $key);

            if (\count($keys) === 1) {
                $merged[$key] = $value;

                continue;
            }

            $specific = $this->getSubArray($specific, $keys, $value);

            unset($specific[$key]);
        }

        return array_replace_recursive($merged, $specific);
    }

    /**
     * @param array<mixed> $configValues
     * @param array<string> $keys
     * @param array<mixed>|bool|float|int|string|null $value
     *
     * @return array<mixed>
     */
    private function getSubArray(array $configValues, array $keys, mixed $value): array
    {
        $key = \array_shift($keys);

        if ($key === null) {
            return $configValues;
        }

        if (empty($keys)) {
            // Configs can be overwritten with sales_channel_id
            $inheritedValuePresent = \array_key_exists($key, $configValues);
            $valueConsideredEmpty = !\is_bool($value) && empty($value);

            if ($inheritedValuePresent && $valueConsideredEmpty) {
                return $configValues;
            }

            $configValues[$key] = $value;
        } else {
            if (!\array_key_exists($key, $configValues)) {
                $configValues[$key] = [];
            }

            $configValues[$key] = $this->getSubArray($configValues[$key], $keys, $value);
        }

        return $configValues;
    }
}
