<?php declare(strict_types=1);

namespace Shopware\Search\Writer;

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

class SearchTablesResource extends Resource
{
    protected const TABLE_FIELD = 'table';
    protected const REFERENZ_TABLE_FIELD = 'referenzTable';
    protected const FOREIGN_KEY_FIELD = 'foreignKey';
    protected const WHERE_FIELD = 'where';

    public function __construct()
    {
        parent::__construct('s_search_tables');
        
        $this->fields[self::TABLE_FIELD] = (new StringField('table'))->setFlags(new Required());
        $this->fields[self::REFERENZ_TABLE_FIELD] = new StringField('referenz_table');
        $this->fields[self::FOREIGN_KEY_FIELD] = new StringField('foreign_key');
        $this->fields[self::WHERE_FIELD] = new StringField('where');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Search\Writer\SearchTablesResource::class
        ];
    }
}
