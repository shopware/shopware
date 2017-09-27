<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class FilterRelationResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const POSITION_FIELD = 'position';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('filter_relation');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['filterGroup'] = new ReferenceField('filterGroupUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterResource::class);
        $this->fields['filterGroupUuid'] = (new FkField('filter_group_uuid', \Shopware\Framework\Write\Resource\FilterResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['filterOption'] = new ReferenceField('filterOptionUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterOptionResource::class);
        $this->fields['filterOptionUuid'] = (new FkField('filter_option_uuid', \Shopware\Framework\Write\Resource\FilterOptionResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterResource::class,
            \Shopware\Framework\Write\Resource\FilterOptionResource::class,
            \Shopware\Framework\Write\Resource\FilterRelationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterRelationWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterRelationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterOptionResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterOptionResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterRelationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterRelationResource::createWrittenEvent($updates, $context));
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
