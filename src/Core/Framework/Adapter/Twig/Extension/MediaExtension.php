<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('core')]
class MediaExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $mediaRepository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('searchMedia', $this->searchMedia(...)),
        ];
    }

    public function searchMedia(array $ids, Context $context): MediaCollection
    {
        if (empty($ids)) {
            return new MediaCollection();
        }

        $criteria = new Criteria($ids);

        /** @var MediaCollection $media */
        $media = $this->mediaRepository
            ->search($criteria, $context)
            ->getEntities();

        return $media;
    }
}
