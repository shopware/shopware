<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class FilterResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const POSITION_FIELD = 'position';
    protected const COMPARABLE_FIELD = 'comparable';
    protected const SORT_MODE_FIELD = 'sortMode';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('filter');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::COMPARABLE_FIELD] = new BoolField('comparable');
        $this->fields[self::SORT_MODE_FIELD] = new IntField('sort_mode');
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Framework\Write\Resource\FilterTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['relations'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterRelationResource::class);
        $this->fields['products'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterResource::class,
            \Shopware\Framework\Write\Resource\FilterTranslationResource::class,
            \Shopware\Framework\Write\Resource\FilterRelationResource::class,
            \Shopware\Product\Writer\Resource\ProductResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterTranslationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterTranslationResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterRelationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterRelationResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates, $context));
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
