<?php declare(strict_types=1);

namespace Shopware\Product\Writer;

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

class ProductNotificationResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const ORDER_NUMBER_FIELD = 'orderNumber';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const MAIL_FIELD = 'mail';
    protected const SEND_FIELD = 'send';
    protected const LANGUAGE_FIELD = 'language';
    protected const SHOP_LINK_FIELD = 'shopLink';

    public function __construct()
    {
        parent::__construct('product_notification');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::ORDER_NUMBER_FIELD] = (new StringField('order_number'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = (new DateField('created_at'))->setFlags(new Required());
        $this->fields[self::MAIL_FIELD] = (new StringField('mail'))->setFlags(new Required());
        $this->fields[self::SEND_FIELD] = (new IntField('send'))->setFlags(new Required());
        $this->fields[self::LANGUAGE_FIELD] = (new StringField('language'))->setFlags(new Required());
        $this->fields[self::SHOP_LINK_FIELD] = (new StringField('shop_link'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\ProductNotificationResource::class
        ];
    }    
    
    public function getDefaults(string $type): array {
        if($type === self::FOR_UPDATE) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if($type === self::FOR_INSERT) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
