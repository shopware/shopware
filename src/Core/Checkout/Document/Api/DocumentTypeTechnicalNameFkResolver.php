<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Api;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.9.0 - reason:remove-fk-resolver - Will be removed.
 */
#[Package('framework')]
class DocumentTypeTechnicalNameFkResolver extends AbstractFkResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getName(): string
    {
        return 'document_type.technical_name';
    }

    /**
     * @param array<FkReference> $map
     *
     * @return array<FkReference>
     */
    public function resolve(array $map): array
    {
        Feature::triggerDeprecationOrThrow('v6.9.0.0', 'DocumentTypeTechnicalNameFkResolver is deprecated and will be removed with document generation v1.');

        $names = \array_map(static fn ($id) => $id->value, $map);

        $names = \array_filter(\array_unique($names));

        if ($names === []) {
            return $map;
        }

        $hash = $this->connection->fetchAllKeyValue(
            'SELECT technical_name, LOWER(HEX(id)) FROM document_type WHERE technical_name IN (:names)',
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
