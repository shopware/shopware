<?php declare(strict_types=1);

namespace Shopware\Media\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class MediaTranslationDetailStruct extends MediaTranslationBasicStruct
{
    /**
     * @var MediaBasicStruct
     */
    protected $media;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getMedia(): MediaBasicStruct
    {
        return $this->media;
    }

    public function setMedia(MediaBasicStruct $media): void
    {
        $this->media = $media;
    }

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
