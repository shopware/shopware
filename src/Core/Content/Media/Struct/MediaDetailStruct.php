<?php declare(strict_types=1);

namespace Shopware\Content\Media\Struct;

use Shopware\Content\Media\Collection\MediaTranslationBasicCollection;
use Shopware\System\User\Struct\UserBasicStruct;

class MediaDetailStruct extends MediaBasicStruct
{
    /**
     * @var UserBasicStruct|null
     */
    protected $user;

    /**
     * @var MediaTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new MediaTranslationBasicCollection();
    }

    public function getUser(): ?UserBasicStruct
    {
        return $this->user;
    }

    public function setUser(?UserBasicStruct $user): void
    {
        $this->user = $user;
    }

    public function getTranslations(): MediaTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(MediaTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
