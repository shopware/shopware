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

class OrderComparisonsResource extends Resource
{
    protected const SESSIONID_FIELD = 'sessionID';
    protected const USERID_FIELD = 'userID';
    protected const ARTICLENAME_FIELD = 'articlename';
    protected const ARTICLEID_FIELD = 'articleID';
    protected const DATUM_FIELD = 'datum';

    public function __construct()
    {
        parent::__construct('s_order_comparisons');
        
        $this->fields[self::SESSIONID_FIELD] = new StringField('sessionID');
        $this->fields[self::USERID_FIELD] = new IntField('userID');
        $this->fields[self::ARTICLENAME_FIELD] = (new StringField('articlename'))->setFlags(new Required());
        $this->fields[self::ARTICLEID_FIELD] = new IntField('articleID');
        $this->fields[self::DATUM_FIELD] = new DateField('datum');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\OrderComparisonsResource::class
        ];
    }
}
