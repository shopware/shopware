<?php declare(strict_types=1);

namespace Shopware\Area\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class AreaResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const ACTIVE_FIELD = 'active';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('area');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Area\Writer\Resource\AreaTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['countries'] = new SubresourceField(\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\Resource\AreaResource::class,
            \Shopware\Area\Writer\Resource\AreaTranslationResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Area\Event\AreaWrittenEvent
    {
        $event = new \Shopware\Area\Event\AreaWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Area\Writer\Resource\AreaResource::class])) {
            $event->addEvent(\Shopware\Area\Writer\Resource\AreaResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Area\Writer\Resource\AreaTranslationResource::class])) {
            $event->addEvent(\Shopware\Area\Writer\Resource\AreaTranslationResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::createWrittenEvent($updates, $context));
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
