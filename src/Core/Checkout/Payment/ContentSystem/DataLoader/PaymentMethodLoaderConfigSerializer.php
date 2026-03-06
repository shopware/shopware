<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\ContentSystem\DataLoader;

use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type PaymentMethodLoaderConfigData from PaymentMethodLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class PaymentMethodLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'payment_method';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $associations = [];
        if (\array_key_exists('associations', $data) && $data['associations'] !== null) {
            if (!\is_array($data['associations'])) {
                throw PaymentException::invalidFieldValueType('associations', 'array', \gettype($data['associations']));
            }
            foreach ($data['associations'] as $i => $association) {
                if (!\is_string($association) || $association === '') {
                    throw PaymentException::invalidFieldValueType('associations.' . $i, 'non-empty string', \gettype($association));
                }
                $associations[] = $association;
            }
        }

        $onlyAvailable = true;
        if (\array_key_exists('onlyAvailable', $data)) {
            if (!\is_bool($data['onlyAvailable'])) {
                throw PaymentException::invalidFieldValueType('onlyAvailable', 'bool', \gettype($data['onlyAvailable']));
            }
            $onlyAvailable = $data['onlyAvailable'];
        }

        return new PaymentMethodLoaderConfig($associations, $onlyAvailable);
    }

    /**
     * @return PaymentMethodLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof PaymentMethodLoaderConfig) {
            throw PaymentException::invalidFieldValueType('config', PaymentMethodLoaderConfig::class, $config::class);
        }

        $data = [];

        if ($config->associations !== []) {
            $data['associations'] = $config->associations;
        }

        if ($config->onlyAvailable !== true) {
            $data['onlyAvailable'] = $config->onlyAvailable;
        }

        return $data;
    }
}
