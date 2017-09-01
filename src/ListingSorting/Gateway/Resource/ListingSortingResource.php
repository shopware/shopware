<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Gateway\Resource;

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

class ListingSortingResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const LABEL_FIELD = 'label';
    protected const ACTIVE_FIELD = 'active';
    protected const DISPLAY_IN_CATEGORIES_FIELD = 'displayInCategories';
    protected const POSITION_FIELD = 'position';
    protected const PAYLOAD_FIELD = 'payload';

    public function __construct()
    {
        parent::__construct('listing_sorting');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::LABEL_FIELD] = (new StringField('label'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::DISPLAY_IN_CATEGORIES_FIELD] = (new BoolField('display_in_categories'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::PAYLOAD_FIELD] = (new LongTextField('payload'))->setFlags(new Required());
        $this->fields['productStreams'] = new SubresourceField(\Shopware\ProductStream\Gateway\Resource\ProductStreamResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\ListingSorting\Gateway\Resource\ListingSortingResource::class,
            \Shopware\ProductStream\Gateway\Resource\ProductStreamResource::class
        ];
    }
}
