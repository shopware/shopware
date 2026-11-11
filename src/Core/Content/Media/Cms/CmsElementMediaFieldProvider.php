<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads the media of a CMS element from its config by a path, which starts with the config field and continues into its static value.
 * Segments are separated by dots, `*` stands for every entry of a list, for example:
 *  - `media` reads the media id of the `media` field
 *  - `sliderItems.*.mediaId` reads the `mediaId` of every item of the `sliderItems` field
 *  - `slider.*.item.media.*.id` reads the `id` of every media of the item of every slider entry
 * The path has to end at a media id or a list of media ids.
 *
 * @final
 */
#[Package('discovery')]
class CmsElementMediaFieldProvider implements CmsElementMediaProviderInterface
{
    private const WILDCARD = '*';

    /**
     * @param string $type type of the CMS element, like `image-slider`
     * @param string $path path to the media ids in the config, like `sliderItems.*.mediaId`
     */
    public function __construct(
        private readonly string $type,
        private readonly string $path,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMediaIds(CmsSlotEntity $slot): array
    {
        $segments = explode('.', $this->path);
        $config = $slot->getFieldConfig()->get((string) array_shift($segments));
        if ($config === null || !$config->isStatic()) {
            return [];
        }

        $values = [$config->getValue()];
        foreach ($segments as $segment) {
            $values = self::walk($values, $segment);
        }

        $mediaIds = [];
        foreach ($values as $value) {
            foreach (\is_array($value) && array_is_list($value) ? $value : [$value] as $mediaId) {
                if (\is_string($mediaId) && Uuid::isValid(strtolower($mediaId))) {
                    $mediaIds[] = $mediaId;
                }
            }
        }

        return $mediaIds;
    }

    /**
     * @param list<mixed> $values
     *
     * @return list<mixed>
     */
    private static function walk(array $values, string $segment): array
    {
        $next = [];
        foreach ($values as $value) {
            if (!\is_array($value)) {
                continue;
            }

            if ($segment === self::WILDCARD) {
                array_push($next, ...array_values($value));
            } elseif (\array_key_exists($segment, $value)) {
                $next[] = $value[$segment];
            }
        }

        return $next;
    }
}
