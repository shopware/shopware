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

class FilterValueResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const VALUE_FIELD = 'value';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('filter_value');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = (new StringField('value'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields['filterProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterProductResource::class);
        $this->fields['option'] = new ReferenceField('optionUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterOptionResource::class);
        $this->fields['optionUuid'] = (new FkField('option_uuid', \Shopware\Framework\Write\Resource\FilterOptionResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', \Shopware\Media\Writer\Resource\MediaResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', \Shopware\Media\Writer\Resource\MediaResource::class, 'uuid'));
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterProductResource::class,
            \Shopware\Framework\Write\Resource\FilterOptionResource::class,
            \Shopware\Media\Writer\Resource\MediaResource::class,
            \Shopware\Framework\Write\Resource\FilterValueResource::class
        ];
    }
}
