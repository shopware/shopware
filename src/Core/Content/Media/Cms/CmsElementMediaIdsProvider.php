<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use Shopware\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads the media of a CMS element from its config by paths, each starts with the config field and continues into its static value.
 * Segments are separated by dots, `*` stands for every entry of a list, for example:
 *  - `media` reads the media id of the `media` field
 *  - `sliderItems.*.mediaId` reads the `mediaId` of every item of the `sliderItems` field
 *  - `slider.*.item.media.*.id` reads the `id` of every media of the item of every slider entry
 * Each path has to end at a media id or a list of media ids.
 *
 * @final
 */
#[Package('discovery')]
class CmsElementMediaIdsProvider
{
    private const WILDCARD = '*';

    /**
     * @param CmsElementResolverInterface $resolver resolver of the CMS element, the provider supports its type
     * @param list<string> $paths paths to the media ids in the config, like `sliderItems.*.mediaId`
     */
    public function __construct(
        private readonly CmsElementResolverInterface $resolver,
        private readonly array $paths,
    ) {
    }

    /**
     * Whether the provider knows the media of the CMS element type, like `image`
     */
    public function supports(string $type): bool
    {
        return $type === $this->resolver->getType();
    }

    /**
     * Returns the ids of the media the element shows from its static config.
     * Mapped values like `category.media` are found through the entity they are mapped from.
     *
     * @param array<string, array{source?: string, value?: mixed}> $config raw config of the element, its fields by name, like `['media' => ['source' => 'static', 'value' => '<media id>']]`, it is not validated
     *
     * @return list<string>
     */
    public function getMediaIds(array $config): array
    {
        $mediaIds = [];
        foreach ($this->paths as $path) {
            array_push($mediaIds, ...$this->getMediaIdsByPath($config, $path));
        }

        return $mediaIds;
    }

    /**
     * @param array<string, array{source?: string, value?: mixed}> $config
     *
     * @return list<string>
     */
    private function getMediaIdsByPath(array $config, string $path): array
    {
        $segments = explode('.', $path);
        $field = $config[array_shift($segments)] ?? null;
        if (!\is_array($field) || ($field['source'] ?? null) !== FieldConfig::SOURCE_STATIC) {
            return [];
        }

        $values = [$field['value'] ?? null];
        foreach ($segments as $segment) {
            $values = $this->walk($values, $segment);
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
    private function walk(array $values, string $segment): array
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
