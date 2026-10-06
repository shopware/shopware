<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Api;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopware\Core\Framework\Api\Sync\FkReference;
use Shopware\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    description: 'Document types are code-registered strings in DocumentV2. Read document.typeName instead of resolving the document_type foreign key.',
)]
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
