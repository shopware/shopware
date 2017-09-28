<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductConfiguratorGroupTranslationResource extends Resource
{
    protected const PRODUCT_CONFIGURATOR_GROUP_UUID_FIELD = 'productConfiguratorGroupUuid';
    protected const LANGUAGE_UUID_FIELD = 'languageUuid';
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('product_configurator_group_translation');

        $this->fields[self::PRODUCT_CONFIGURATOR_GROUP_UUID_FIELD] = (new StringField('product_configurator_group_uuid'))->setFlags(new Required());
        $this->fields[self::LANGUAGE_UUID_FIELD] = (new StringField('language_uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorGroupTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductConfiguratorGroupTranslationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorGroupTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorGroupTranslationResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorGroupTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
