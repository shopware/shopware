<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Event\ProductEsdWrittenEvent;

class ProductEsdWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const PRODUCT_DETAIL_UUID_FIELD = 'productDetailUuid';
    protected const FILE_FIELD = 'file';
    protected const HAS_SERIALS_FIELD = 'hasSerials';
    protected const ALLOW_NOTIFICATION_FIELD = 'allowNotification';
    protected const MAX_DOWNLOADS_FIELD = 'maxDownloads';

    public function __construct()
    {
        parent::__construct('product_esd');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PRODUCT_DETAIL_UUID_FIELD] = (new StringField('product_detail_uuid'))->setFlags(new Required());
        $this->fields[self::FILE_FIELD] = (new StringField('file'))->setFlags(new Required());
        $this->fields[self::HAS_SERIALS_FIELD] = new BoolField('has_serials');
        $this->fields[self::ALLOW_NOTIFICATION_FIELD] = new IntField('allow_notification');
        $this->fields[self::MAX_DOWNLOADS_FIELD] = new IntField('max_downloads');
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['serials'] = new SubresourceField(ProductEsdSerialWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            ProductWriteResource::class,
            self::class,
            ProductEsdSerialWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductEsdWrittenEvent
    {
        $event = new ProductEsdWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ProductWriteResource::class])) {
            $event->addEvent(ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductEsdSerialWriteResource::class])) {
            $event->addEvent(ProductEsdSerialWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
