<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaTranslation\Struct;

use Shopware\Application\Language\Struct\LanguageBasicStruct;
use Shopware\Content\Media\Struct\MediaBasicStruct;

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
