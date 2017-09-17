<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class TaxAreaRuleTranslationResource extends Resource
{
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('tax_area_rule_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['taxAreaRule'] = new ReferenceField('taxAreaRuleUuid', 'uuid', \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class);
        $this->primaryKeyFields['taxAreaRuleUuid'] = (new FkField('tax_area_rule_uuid', \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\TaxAreaRule\Event\TaxAreaRuleTranslationWrittenEvent
    {
        $event = new \Shopware\TaxAreaRule\Event\TaxAreaRuleTranslationWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
