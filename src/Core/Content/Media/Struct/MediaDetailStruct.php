<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Struct;

use Shopware\Core\Content\Media\Aggregate\MediaTranslation\Collection\MediaTranslationBasicCollection;
use Shopware\Core\System\User\Struct\UserBasicStruct;

class MediaDetailStruct extends MediaBasicStruct
{
    /**
     * @var UserBasicStruct|null
     */
    protected $user;

    /**
     * @var \Shopware\Core\Content\Media\Aggregate\MediaTranslation\Collection\MediaTranslationBasicCollection
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
