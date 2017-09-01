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

class MultiEditBackupResource extends Resource
{
    protected const FILTER_STRING_FIELD = 'filterString';
    protected const OPERATION_STRING_FIELD = 'operationString';
    protected const ITEMS_FIELD = 'items';
    protected const DATE_FIELD = 'date';
    protected const SIZE_FIELD = 'size';
    protected const PATH_FIELD = 'path';
    protected const HASH_FIELD = 'hash';

    public function __construct()
    {
        parent::__construct('s_multi_edit_backup');
        
        $this->fields[self::FILTER_STRING_FIELD] = (new LongTextField('filter_string'))->setFlags(new Required());
        $this->fields[self::OPERATION_STRING_FIELD] = (new LongTextField('operation_string'))->setFlags(new Required());
        $this->fields[self::ITEMS_FIELD] = (new IntField('items'))->setFlags(new Required());
        $this->fields[self::DATE_FIELD] = new DateField('date');
        $this->fields[self::SIZE_FIELD] = (new IntField('size'))->setFlags(new Required());
        $this->fields[self::PATH_FIELD] = (new StringField('path'))->setFlags(new Required());
        $this->fields[self::HASH_FIELD] = (new StringField('hash'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\MultiEditBackupResource::class
        ];
    }
}
