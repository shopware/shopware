<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class PluginCategoryResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const LOCALE_FIELD = 'locale';
    protected const NAME_FIELD = 'name';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('plugin_category');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->primaryKeyFields[self::LOCALE_FIELD] = (new StringField('locale'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new LongTextField('name'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', \Shopware\Framework\Write\Resource\PluginCategoryResource::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', \Shopware\Framework\Write\Resource\PluginCategoryResource::class, 'uuid'));
        $this->fields['parent'] = new SubresourceField(\Shopware\Framework\Write\Resource\PluginCategoryResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\PluginCategoryResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\PluginCategoryWrittenEvent
    {
        $event = new \Shopware\Framework\Event\PluginCategoryWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\PluginCategoryResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\PluginCategoryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\PluginCategoryResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\PluginCategoryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\PluginCategoryResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\PluginCategoryResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
