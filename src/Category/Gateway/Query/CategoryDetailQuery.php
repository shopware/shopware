<?php

namespace Shopware\Category\Gateway\Query;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\FieldHelper;

class CategoryDetailQuery extends CategoryIdentityQuery
{
    public function __construct(Connection $connection, FieldHelper $fieldHelper, TranslationContext $context)
    {
        parent::__construct($connection, $fieldHelper, $context);

        //assigned category media
        $this->addSelect($fieldHelper->getMediaFields());
        $this->leftJoin('category', 's_media', 'media', 'media.id = category.media_id');
        $this->leftJoin('media', 's_media_album_settings', 'mediaSettings', 'mediaSettings.albumID = media.albumID');
        $this->leftJoin('media', 's_media_attributes', 'mediaAttribute', 'mediaAttribute.mediaID = media.id');
        $fieldHelper->addMediaTranslation($this, $context);

        //related product stream
        $this->addSelect($fieldHelper->getRelatedProductStreamFields());
        $this->leftJoin('category', 's_product_streams', 'stream', 'category.stream_id = stream.id');
        $this->leftJoin('stream', 's_product_streams_attributes', 'productStreamAttribute', 'stream.id = productStreamAttribute.streamId');
        $fieldHelper->addProductStreamTranslation($this, $context);
    }
}