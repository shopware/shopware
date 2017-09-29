<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class TaxAreaRuleTranslationWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('tax_area_rule_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['taxAreaRule'] = new ReferenceField('taxAreaRuleUuid', 'uuid', \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class);
        $this->primaryKeyFields['taxAreaRuleUuid'] = (new FkField('tax_area_rule_uuid', \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\TaxAreaRule\Event\TaxAreaRuleTranslationWrittenEvent
    {
        $event = new \Shopware\TaxAreaRule\Event\TaxAreaRuleTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
