<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ListingFacetWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

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
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(ListingFacetTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            ListingFacetTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ListingFacetWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ListingFacetWrittenEvent($uuids, $context, $rawData, $errors);

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
