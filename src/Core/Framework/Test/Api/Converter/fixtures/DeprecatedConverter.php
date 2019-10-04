<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Converter\fixtures;

use Shopware\Core\Framework\Api\Converter\ApiConverterInterface;

class DeprecatedConverter implements ApiConverterInterface
{
    private const DEPRECATED_FIELD_NAMES = [
        'price',
        'tax',
        'taxId'
    ];

    private const NEW_FIELD_NAMES = [
        'prices',
        'product',
        'productId'
    ];

    public function getProcessedEntityName(): string
    {
        return 'deprecated';
    }

    public function getDeprecatedApiVersion(): int
    {
        return 2;
    }

    public function convertEntityPayloadToCurrentVersion(array $payload): array
    {
        // macht es überhaupt sinn die payload zu convertieren?
        // z.B. wenn es kein map-bares replacement gibt können wir nix konvertieren und schreiben zwangsläufig den deprecateten wert
        // siehe `taxId` und `tax` sind deprecated können aber nicht konvertiert werden da sie einfach wegfallen
        // was bringt uns die Konvertierung, vor allem weil sie zwangsläufig in der DB auch schon stattfindet
        if (array_key_exists('price', $payload)) {
            $payload['prices'] = [$payload['price']];

            unset($payload['price']);
        }

        return $payload;
    }

    public function isFieldFromFuture(string $fieldName): bool
    {
        if (in_array($fieldName, self::NEW_FIELD_NAMES)) {
            return true;
        }

        return false;
    }

    public function isFieldDeprecated(string $fieldName): bool
    {
        if (in_array($fieldName, self::DEPRECATED_FIELD_NAMES)) {
            return true;
        }

        return false;
    }
}
