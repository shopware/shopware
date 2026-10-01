<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Api;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ShippingMethodTechnicalNameFkResolver extends AbstractFkResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getName(): string
    {
        return 'shipping_method.technical_name';
    }

    /**
     * @param array<FkReference> $map
     *
     * @return array<FkReference>
     */
    public function resolve(array $map): array
    {
        $names = \array_map(static fn ($id) => $id->value, $map);

        $names = \array_filter(\array_unique($names));

        if ($names === []) {
            return $map;
        }

        $hash = $this->connection->fetchAllKeyValue(
            'SELECT technical_name, LOWER(HEX(id)) FROM shipping_method WHERE technical_name IN (:names)',
            ['names' => $names],
            ['names' => ArrayParameterType::STRING]
        );

        foreach ($map as $reference) {
            if (isset($hash[$reference->value])) {
                $reference->resolved = $hash[$reference->value];
            }
        }

        return $map;
    }
}
