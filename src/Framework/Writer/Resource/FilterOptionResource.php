<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class FilterOptionResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const FILTERABLE_FIELD = 'filterable';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('filter_option');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::FILTERABLE_FIELD] = new BoolField('filterable');
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Framework\Write\Resource\FilterOptionTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['filterRelations'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterRelationResource::class);
        $this->fields['filterValues'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterValueResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterOptionResource::class,
            \Shopware\Framework\Write\Resource\FilterOptionTranslationResource::class,
            \Shopware\Framework\Write\Resource\FilterRelationResource::class,
            \Shopware\Framework\Write\Resource\FilterValueResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterOptionWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterOptionWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterOptionResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterOptionResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterOptionTranslationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterOptionTranslationResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterRelationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterRelationResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueResource::createWrittenEvent($updates, $context));
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
