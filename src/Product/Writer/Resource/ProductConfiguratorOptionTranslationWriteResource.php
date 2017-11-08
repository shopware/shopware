<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Event\ProductConfiguratorOptionTranslationWrittenEvent;

class ProductConfiguratorOptionTranslationWriteResource extends WriteResource
{
    protected const PRODUCT_CONFIGURATOR_OPTION_UUID_FIELD = 'productConfiguratorOptionUuid';
    protected const LANGUAGE_UUID_FIELD = 'languageUuid';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('product_configurator_option_translation');

        $this->fields[self::PRODUCT_CONFIGURATOR_OPTION_UUID_FIELD] = (new StringField('product_configurator_option_uuid'))->setFlags(new Required());
        $this->fields[self::LANGUAGE_UUID_FIELD] = (new StringField('language_uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductConfiguratorOptionTranslationWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ProductConfiguratorOptionTranslationWrittenEvent($uuids, $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
