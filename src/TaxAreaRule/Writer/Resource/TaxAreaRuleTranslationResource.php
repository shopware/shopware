<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\TaxAreaRule\Event\TaxAreaRuleTranslationWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\TaxAreaRule\Event\TaxAreaRuleTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleTranslationResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
