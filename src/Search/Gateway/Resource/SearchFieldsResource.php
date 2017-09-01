<?php declare(strict_types=1);

namespace Shopware\Search\Gateway\Resource;

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

class SearchFieldsResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const RELEVANCE_FIELD = 'relevance';
    protected const FIELD_FIELD = 'field';
    protected const TABLEID_FIELD = 'tableID';

    public function __construct()
    {
        parent::__construct('s_search_fields');
        
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::RELEVANCE_FIELD] = (new IntField('relevance'))->setFlags(new Required());
        $this->fields[self::FIELD_FIELD] = (new StringField('field'))->setFlags(new Required());
        $this->fields[self::TABLEID_FIELD] = (new IntField('tableID'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Search\Gateway\Resource\SearchFieldsResource::class
        ];
    }
}
