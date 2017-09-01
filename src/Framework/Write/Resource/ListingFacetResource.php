<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class ListingFacetResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const ACTIVE_FIELD = 'active';
    protected const UNIQUE_KEY_FIELD = 'uniqueKey';
    protected const DISPLAY_IN_CATEGORIES_FIELD = 'displayInCategories';
    protected const DELETABLE_FIELD = 'deletable';
    protected const POSITION_FIELD = 'position';
    protected const NAME_FIELD = 'name';
    protected const PAYLOAD_FIELD = 'payload';

    public function __construct()
    {
        parent::__construct('listing_facet');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::UNIQUE_KEY_FIELD] = new StringField('unique_key');
        $this->fields[self::DISPLAY_IN_CATEGORIES_FIELD] = (new BoolField('display_in_categories'))->setFlags(new Required());
        $this->fields[self::DELETABLE_FIELD] = (new BoolField('deletable'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::PAYLOAD_FIELD] = (new LongTextField('payload'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\ListingFacetResource::class
        ];
    }
}
