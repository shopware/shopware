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

class SearchIndexResource extends Resource
{
    protected const KEYWORDID_FIELD = 'keywordID';
    protected const FIELDID_FIELD = 'fieldID';
    protected const ELEMENTID_FIELD = 'elementID';

    public function __construct()
    {
        parent::__construct('s_search_index');
        
        $this->primaryKeyFields[self::KEYWORDID_FIELD] = (new IntField('keywordID'))->setFlags(new Required());
        $this->primaryKeyFields[self::FIELDID_FIELD] = (new IntField('fieldID'))->setFlags(new Required());
        $this->primaryKeyFields[self::ELEMENTID_FIELD] = (new IntField('elementID'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Search\Gateway\Resource\SearchIndexResource::class
        ];
    }
}
