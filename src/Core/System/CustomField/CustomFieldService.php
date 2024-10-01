<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('core')]
class CustomFieldService implements EventSubscriberInterface, ResetInterface
{
    // Custom field names should be valid twig variable names (https://github.com/twigphp/Twig/blob/21df1ad7824ced2abcbd33863f04c6636674481f/src/Lexer.php#L46)
    public const CUSTOM_FIELD_NAME_PATTERN = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/';

    /**
     * @var array<string, mixed>
     */
    private array $customFields = [];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getCustomField(string $attributeName): ?Field
    {
        $type = $this->getCustomFields()[$attributeName] ?? null;

        if (!$type) {
            return null;
        }

        return match ($type) {
            CustomFieldTypes::INT => (new IntField($attributeName, $attributeName))->addFlags(new ApiAware()),
            CustomFieldTypes::FLOAT => (new FloatField($attributeName, $attributeName))->addFlags(new ApiAware()),
            CustomFieldTypes::BOOL => (new BoolField($attributeName, $attributeName))->addFlags(new ApiAware()),
            CustomFieldTypes::DATETIME => (new DateTimeField($attributeName, $attributeName))->addFlags(new ApiAware()),
            CustomFieldTypes::TEXT => (new LongTextField($attributeName, $attributeName))->addFlags(new ApiAware()),
            CustomFieldTypes::HTML => (new LongTextField($attributeName, $attributeName))->addFlags(new ApiAware(), new AllowHtml()),
            CustomFieldTypes::PRICE => (new PriceField($attributeName, $attributeName))->addFlags(new ApiAware()),
            default => (new JsonField($attributeName, $attributeName))->addFlags(new ApiAware()),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomFieldEvents::CUSTOM_FIELD_DELETED_EVENT => 'reset',
            CustomFieldEvents::CUSTOM_FIELD_WRITTEN_EVENT => 'reset',
            EntityWriteEvent::class => 'validateBeforeWrite',
        ];
    }

    public function validateBeforeWrite(EntityWriteEvent $event): void
    {
        /** @deprecated tag:v6.7.0 - remove if condition */
        if (!Feature::isActive('v6.7.0.0')) {
            return;
        }

        $commands = $event->getCommands();

        if (empty($commands)) {
            return;
        }

        $customFieldCommands = array_filter($commands, function ($command) {
            return $command->getEntityName() === CustomFieldSetDefinition::ENTITY_NAME
                || $command->getEntityName() === CustomFieldDefinition::ENTITY_NAME;
        });

        foreach ($customFieldCommands as $command) {
            $this->validateCustomFieldName($command->getPayload());
        }
    }

    public function reset(): void
    {
        $this->customFields = [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateCustomFieldName(array $payload): void
    {
        $name = $payload['name'] ?? null;

        if (!$name) {
            return;
        }

        if (!preg_match(self::CUSTOM_FIELD_NAME_PATTERN, $name)) {
            throw CustomFieldException::customFieldNameInvalid($name);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getCustomFields(): array
    {
        if (!empty($this->customFields)) {
            return $this->customFields;
        }

        /** @var array<string, mixed> $customField */
        $customField = $this->connection->fetchAllKeyValue('SELECT `name`, `type` FROM `custom_field` WHERE `active` = 1');

        if (!empty($customField)) {
            $this->customFields = $customField;
        }

        return $this->customFields;
    }
}
