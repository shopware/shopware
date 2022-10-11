<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class CustomFieldService implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string>|null
     */
    private ?array $customFields = null;

    private Connection $connection;

    /**
     * @internal
     */
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
                return (new IntField($attributeName, $attributeName))->addFlags(new ApiAware());

            case CustomFieldTypes::FLOAT:
                return (new FloatField($attributeName, $attributeName))->addFlags(new ApiAware());

            case CustomFieldTypes::BOOL:
                return (new BoolField($attributeName, $attributeName))->addFlags(new ApiAware());

            case CustomFieldTypes::DATETIME:
                return (new DateTimeField($attributeName, $attributeName))->addFlags(new ApiAware());

            case CustomFieldTypes::TEXT:
                return (new LongTextField($attributeName, $attributeName))->addFlags(new ApiAware());

            case CustomFieldTypes::HTML:
                return (new LongTextField($attributeName, $attributeName))->addFlags(new ApiAware(), new AllowHtml());

            case CustomFieldTypes::PRICE:
                return (new PriceField($attributeName, $attributeName))->addFlags(new ApiAware());

            case CustomFieldTypes::JSON:
            default:
                return (new JsonField($attributeName, $attributeName))->addFlags(new ApiAware());
        }
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomFieldEvents::CUSTOM_FIELD_DELETED_EVENT => 'reset',
            CustomFieldEvents::CUSTOM_FIELD_WRITTEN_EVENT => 'reset',
        ];
    }

    public function reset(): void
    {
        $this->customFields = null;
    }

    /**
     * @return array<string>
     */
    private function getCustomFields(): array
    {
        if ($this->customFields !== null) {
            return $this->customFields;
        }

        $this->customFields = $this->connection->fetchAllKeyValue('SELECT `name`, `type` FROM `custom_field` WHERE `active` = 1');

        return $this->customFields;
    }
}
