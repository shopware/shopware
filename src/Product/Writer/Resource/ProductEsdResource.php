<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

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

class ProductEsdResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const PRODUCT_DETAIL_UUID_FIELD = 'productDetailUuid';
    protected const FILE_FIELD = 'file';
    protected const SERIALS_FIELD = 'serials';
    protected const NOTIFICATION_FIELD = 'notification';
    protected const MAX_DOWNLOADS_FIELD = 'maxDownloads';
    protected const CREATED_AT_FIELD = 'createdAt';

    public function __construct()
    {
        parent::__construct('product_esd');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PRODUCT_DETAIL_UUID_FIELD] = (new StringField('product_detail_uuid'))->setFlags(new Required());
        $this->fields[self::FILE_FIELD] = (new StringField('file'))->setFlags(new Required());
        $this->fields[self::SERIALS_FIELD] = new IntField('serials');
        $this->fields[self::NOTIFICATION_FIELD] = new IntField('notification');
        $this->fields[self::MAX_DOWNLOADS_FIELD] = new IntField('max_downloads');
        $this->fields[self::CREATED_AT_FIELD] = (new DateField('created_at'))->setFlags(new Required());
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['serials'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductEsdSerialResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\Product\Writer\Resource\ProductEsdResource::class,
            \Shopware\Product\Writer\Resource\ProductEsdSerialResource::class
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
