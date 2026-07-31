<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Type;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Loads document types registered by active apps via the `app_document_type` aggregate.
 *
 * @internal
 */
#[Package('after-sales')]
final class AppDocumentTypeLoader implements ResetInterface
{
    /**
     * @var array<string, list<string>>|null
     */
    private ?array $typesByName = null;

    /**
     * @var array<string, array<string, scalar>>|null
     */
    private ?array $configByName = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, list<string>>
     */
    public function load(): array
    {
        $this->fetch();

        \assert($this->typesByName !== null);

        return $this->typesByName;
    }

    /**
     * @return array<string, scalar>
     */
    public function loadConfig(string $technicalName): array
    {
        $this->fetch();

        \assert($this->configByName !== null);

        return $this->configByName[$technicalName] ?? [];
    }

    public function reset(): void
    {
        $this->typesByName = null;
        $this->configByName = null;
    }

    private function fetch(): void
    {
        if ($this->typesByName !== null) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT `app_document_type`.`technical_name`, `app_document_type`.`formats`, `app_document_type`.`config`
            FROM `app_document_type`
            INNER JOIN `app` ON `app`.`id` = `app_document_type`.`app_id`
            WHERE `app`.`active` = 1'
        );

        $validFormats = array_column(DocumentFormat::cases(), 'value');

        $typesByName = [];
        $configByName = [];

        foreach ($rows as $row) {
            $technicalName = (string) $row['technical_name'];

            // should never shadow core type
            if (DocumentType::tryFrom($technicalName) !== null) {
                continue;
            }

            /** @var list<string> $declaredFormats */
            $declaredFormats = Json::decodeToList((string) $row['formats']);
            $formats = array_values(array_intersect($declaredFormats, $validFormats));

            if ($formats !== []) {
                $typesByName[$technicalName] = $formats;
            }

            $configByName[$technicalName] = $row['config'] !== null
                ? Json::decodeToArray((string) $row['config'])
                : [];
        }

        $this->typesByName = $typesByName;
        $this->configByName = $configByName;
    }
}
