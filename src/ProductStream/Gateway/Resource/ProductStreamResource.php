<?php declare(strict_types=1);

namespace Shopware\ProductStream\Gateway\Resource;

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

class ProductStreamResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const CONDITIONS_FIELD = 'conditions';
    protected const TYPE_FIELD = 'type';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('product_stream');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::CONDITIONS_FIELD] = new LongTextField('conditions');
        $this->fields[self::TYPE_FIELD] = new IntField('type');
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields['listingSorting'] = new ReferenceField('listingSortingUuid', 'uuid', \Shopware\ListingSorting\Gateway\Resource\ListingSortingResource::class);
        $this->fields['listingSortingUuid'] = (new FkField('listing_sorting_uuid', \Shopware\ListingSorting\Gateway\Resource\ListingSortingResource::class, 'uuid'));
        $this->fields['assignments'] = new SubresourceField(\Shopware\ProductStream\Gateway\Resource\ProductStreamAssignmentResource::class);
        $this->fields['tabs'] = new SubresourceField(\Shopware\ProductStream\Gateway\Resource\ProductStreamTabResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\ListingSorting\Gateway\Resource\ListingSortingResource::class,
            \Shopware\ProductStream\Gateway\Resource\ProductStreamResource::class,
            \Shopware\ProductStream\Gateway\Resource\ProductStreamAssignmentResource::class,
            \Shopware\ProductStream\Gateway\Resource\ProductStreamTabResource::class
        ];
    }
}
