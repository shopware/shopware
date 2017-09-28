<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
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

    public function __construct()
    {
        parent::__construct('plugin_category');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->primaryKeyFields[self::LOCALE_FIELD] = (new StringField('locale'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new LongTextField('name'))->setFlags(new Required());
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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\PluginCategoryWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\PluginCategoryWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\PluginCategoryResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
