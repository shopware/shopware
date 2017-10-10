<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Event\ProductConfiguratorGroupTranslationWrittenEvent;

class ProductConfiguratorGroupTranslationWriteResource extends WriteResource
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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductConfiguratorGroupTranslationWrittenEvent
    {
        $event = new ProductConfiguratorGroupTranslationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
