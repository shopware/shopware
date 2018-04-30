<?php declare(strict_types=1);

namespace Shopware\Api\Media\Event\MediaTranslation;

use Shopware\Api\Media\Collection\MediaTranslationBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class MediaTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'media_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MediaTranslationBasicCollection
     */
    protected $mediaTranslations;

    public function __construct(MediaTranslationBasicCollection $mediaTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->mediaTranslations = $mediaTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getMediaTranslations(): MediaTranslationBasicCollection
    {
        return $this->mediaTranslations;
    }
}
