<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ListingFacetWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const ACTIVE_FIELD = 'active';
    protected const UNIQUE_KEY_FIELD = 'uniqueKey';
    protected const DISPLAY_IN_CATEGORIES_FIELD = 'displayInCategories';
    protected const DELETABLE_FIELD = 'deletable';
    protected const POSITION_FIELD = 'position';
    protected const PAYLOAD_FIELD = 'payload';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('listing_facet');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::UNIQUE_KEY_FIELD] = new StringField('unique_key');
        $this->fields[self::DISPLAY_IN_CATEGORIES_FIELD] = new BoolField('display_in_categories');
        $this->fields[self::DELETABLE_FIELD] = new BoolField('deletable');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::PAYLOAD_FIELD] = (new LongTextField('payload'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Framework\Write\Resource\ListingFacetTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\ListingFacetWriteResource::class,
            \Shopware\Framework\Write\Resource\ListingFacetTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\ListingFacetWrittenEvent
    {
        $event = new \Shopware\Framework\Event\ListingFacetWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\ListingFacetWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ListingFacetWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\ListingFacetTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ListingFacetTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
