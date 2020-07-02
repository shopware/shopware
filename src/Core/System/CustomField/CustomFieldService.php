<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomFieldService implements EventSubscriberInterface
{
    /**
     * @var string[]|null
     */
    private $customFields;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getCustomField(string $attributeName): ?Field
    {
        $type = $this->getCustomFields()[$attributeName] ?? null;
        if (!$type) {
            return null;
        }

        switch ($type) {
            case CustomFieldTypes::INT:
                return new IntField($attributeName, $attributeName);

            case CustomFieldTypes::FLOAT:
                return new FloatField($attributeName, $attributeName);

            case CustomFieldTypes::BOOL:
                return new BoolField($attributeName, $attributeName);

            case CustomFieldTypes::DATETIME:
                return new DateTimeField($attributeName, $attributeName);

            case CustomFieldTypes::TEXT:
                return new LongTextField($attributeName, $attributeName);

            case CustomFieldTypes::HTML:
                return (new LongTextField($attributeName, $attributeName))->addFlags(new AllowHtml());

            case CustomFieldTypes::JSON:
            default:
                return new JsonField($attributeName, $attributeName);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomFieldEvents::CUSTOM_FIELD_DELETED_EVENT => 'invalidateCache',
            CustomFieldEvents::CUSTOM_FIELD_WRITTEN_EVENT => 'invalidateCache',
        ];
    }

    /**
     * @internal
     */
    public function invalidateCache(): void
    {
        $this->customFields = null;
    }

    /**
     * @return string[]
     */
    private function getCustomFields(): array
    {
        if ($this->customFields !== null) {
            return $this->customFields;
        }

        $fields = $this->connection->fetchAll('SELECT `name`, `type` FROM `custom_field` WHERE `active` = 1');

        $this->customFields = FetchModeHelper::keyPair($fields);

        return $this->customFields;
    }
}
