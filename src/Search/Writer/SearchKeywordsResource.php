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

class SearchKeywordsResource extends Resource
{
    protected const KEYWORD_FIELD = 'keyword';
    protected const SOUNDEX_FIELD = 'soundex';

    public function __construct()
    {
        parent::__construct('s_search_keywords');
        
        $this->fields[self::KEYWORD_FIELD] = (new StringField('keyword'))->setFlags(new Required());
        $this->fields[self::SOUNDEX_FIELD] = new StringField('soundex');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Search\Writer\SearchKeywordsResource::class
        ];
    }
}
