<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('storefront')]
class ResolvedConfigLoader extends AbstractResolvedConfigLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly ThemeService $service
    ) {
    }

    public function getDecorated(): AbstractResolvedConfigLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $themeId, SalesChannelContext $context): array
    {
        $config = $this->service->getThemeConfiguration($themeId, false, $context->getContext());
        $resolvedConfig = [];
        $mediaItems = [];
        if (!\array_key_exists('fields', $config)) {
            return [];
        }

        foreach ($config['fields'] as $key => $data) {
            if ($data['type'] === 'media' && $data['value'] && Uuid::isValid($data['value'])) {
                $mediaItems[$data['value']][] = $key;
            }
            $resolvedConfig[$key] = $data['value'];
        }

        $result = new MediaCollection();

        /** @var array<string> $mediaIds */
        $mediaIds = array_keys($mediaItems);
        if (!empty($mediaIds)) {
            $criteria = new Criteria($mediaIds);
            $criteria->setTitle('theme-service::resolve-media');
            $result = $this->repository->search($criteria, $context->getContext());
        }

        foreach ($result as $media) {
            if (!\array_key_exists($media->getId(), $mediaItems)) {
                continue;
            }

            foreach ($mediaItems[$media->getId()] as $key) {
                $resolvedConfig[$key] = $media->getUrl();
            }
        }

        return $resolvedConfig;
    }
}
