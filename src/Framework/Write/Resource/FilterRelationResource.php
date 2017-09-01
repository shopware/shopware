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

class FilterRelationResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('filter_relation');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
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
            \Shopware\Framework\Write\Resource\FilterRelationResource::class
        ];
    }
}
