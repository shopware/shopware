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

class OrderEsdResource extends Resource
{
    protected const SERIALID_FIELD = 'serialID';
    protected const ESDID_FIELD = 'esdID';
    protected const USERID_FIELD = 'userID';
    protected const ORDERID_FIELD = 'orderID';
    protected const ORDERDETAILSID_FIELD = 'orderdetailsID';
    protected const DATUM_FIELD = 'datum';

    public function __construct()
    {
        parent::__construct('s_order_esd');
        
        $this->fields[self::SERIALID_FIELD] = new IntField('serialID');
        $this->fields[self::ESDID_FIELD] = new IntField('esdID');
        $this->fields[self::USERID_FIELD] = new IntField('userID');
        $this->fields[self::ORDERID_FIELD] = new IntField('orderID');
        $this->fields[self::ORDERDETAILSID_FIELD] = new IntField('orderdetailsID');
        $this->fields[self::DATUM_FIELD] = (new DateField('datum'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\OrderEsdResource::class
        ];
    }
}
