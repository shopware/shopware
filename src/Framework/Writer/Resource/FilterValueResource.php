<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class FilterValueResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const POSITION_FIELD = 'position';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';
    protected const VALUE_FIELD = 'value';

    public function __construct()
    {
        parent::__construct('filter_value');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['filterProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterProductResource::class);
        $this->fields['option'] = new ReferenceField('optionUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterOptionResource::class);
        $this->fields['optionUuid'] = (new FkField('option_uuid', \Shopware\Framework\Write\Resource\FilterOptionResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', \Shopware\Media\Writer\Resource\MediaResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', \Shopware\Media\Writer\Resource\MediaResource::class, 'uuid'));
        $this->fields[self::VALUE_FIELD] = new TranslatedField('value', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Framework\Write\Resource\FilterValueTranslationResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterProductResource::class,
            \Shopware\Framework\Write\Resource\FilterOptionResource::class,
            \Shopware\Media\Writer\Resource\MediaResource::class,
            \Shopware\Framework\Write\Resource\FilterValueResource::class,
            \Shopware\Framework\Write\Resource\FilterValueTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterValueWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterValueWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterProductResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterProductResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterOptionResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterOptionResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueTranslationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueTranslationResource::createWrittenEvent($updates, $context));
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
