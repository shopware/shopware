<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Writer\Resource\ShopWriteResource;
use Shopware\TaxAreaRule\Event\TaxAreaRuleTranslationWrittenEvent;

class TaxAreaRuleTranslationWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('tax_area_rule_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['taxAreaRule'] = new ReferenceField('taxAreaRuleUuid', 'uuid', TaxAreaRuleWriteResource::class);
        $this->primaryKeyFields['taxAreaRuleUuid'] = (new FkField('tax_area_rule_uuid', TaxAreaRuleWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            TaxAreaRuleWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): TaxAreaRuleTranslationWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new TaxAreaRuleTranslationWrittenEvent($uuids, $context, $rawData, $errors);

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
