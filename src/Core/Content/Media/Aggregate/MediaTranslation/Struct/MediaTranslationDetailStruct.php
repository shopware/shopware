<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaTranslation\Struct;

use Shopware\Core\Content\Media\Struct\MediaBasicStruct;
use Shopware\Core\System\Language\Struct\LanguageBasicStruct;

class MediaTranslationDetailStruct extends MediaTranslationBasicStruct
{
    /**
     * @var MediaBasicStruct
     */
    protected $media;

    /**
     * @var LanguageBasicStruct
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

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
