<?php

namespace Shopware\Storefront\Twig\Components;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[Package('storefront')]
#[AsTwigComponent('carousel')]
class Carousel
{
    public CmsSlotEntity $element;
    /** @var array<string, FieldConfig> */
    public array $config;
    public bool $ride = true;
    public bool $description = false;

    /**
     * @return array<MediaEntity|null>
     * @used-by getMediaItems
     */
    public function getMediaItems(): array
    {
        $mediaItems = [];
        /** @var ImageSliderStruct $data */
        $data = $this->element->getData();
        if ($data && $data->getSliderItems() !== null) {
            foreach ($data->getSliderItems() as $sliderItem) {
                $mediaItems[] = $sliderItem->getMedia();
            }
        }

        return $mediaItems;
    }

    /**
     * @used-by getAutoSlide
     */
    public function getAutoSlide(): bool
    {
        return (isset($this->config['autoSlide']) && $this->config['autoSlide']->getValue());
    }


    /**
     * @used-by getDotsNavigation
     */
    public function getDotsNavigation(): bool
    {
        $dotsNavigation = true;
        if (isset($this->config['navigationDots'])) {
            $dotsNavigationValue = $this->config['navigationDots']->getValue();
            if ($dotsNavigationValue === 'none') {
                $dotsNavigation = false;
            }
        }

        return $dotsNavigation;
    }

    /**
     * @used-by getDotsNavigation
     */
    public function getNavigationArrows(): bool
    {
        $dotsNavigation = true;
        if (isset($this->config['navigationArrows'])) {
            $dotsNavigationValue = $this->config['navigationArrows']->getValue();
            if ($dotsNavigationValue === 'none') {
                $dotsNavigation = false;
            }
        }

        return $dotsNavigation;
    }

    /**
     * @used-by getRide
     */
    public function getRide(): string
    {
        return $this->ride ? "true" : "false";
    }

    /**
     * @used-by getDescription
     */
    public function getDescription(): bool
    {
        return $this->description;
    }

    /**
     * @used-by getMediaAltText
     */
    public function getMediaAltText(MediaEntity $mediaEntity): string
    {
        $altText = $mediaEntity->getAlt() ?? '';
        $translated = $mediaEntity->getTranslated();
        if (isset($translated['alt']) && $translated['alt'] !== '') {
            $altText = $translated['alt'];
        }

        return ($altText !== '') ? $altText : $mediaEntity->getFileName();
    }

    /**
     * @used-by getMediaTitle
     */
    public function getMediaTitle(MediaEntity $mediaEntity): string
    {
        $title = $mediaEntity->getTitle() ?? '';
        $translated = $mediaEntity->getTranslated();
        if (isset($translated['title']) && $translated['title'] !== '') {
            $title = $translated['title'];
        }

        return $title;
    }
}
