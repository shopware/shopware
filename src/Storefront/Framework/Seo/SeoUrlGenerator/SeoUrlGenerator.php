<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlGenerator;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\Slugify;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

abstract class SeoUrlGenerator implements SeoUrlGeneratorInterface
{
    public const ESCAPE_SLUGIFY = 'slugifyurlencode';
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var string
     */
    protected $routeName;

    /**
     * @var SalesChannelContextFactory
     */
    protected $salesChannelContextFactory;

    /**
     * @var EntityRepositoryInterface
     */
    protected $salesChannelRepository;

    public function __construct(EntityRepositoryInterface $salesChannelRepository, SalesChannelContextFactory $salesChannelContextFactory, Slugify $slugify, RouterInterface $router, string $routeName)
    {
        $this->twig = new Environment(new ArrayLoader());
        $this->twig->setCache(false);
        $this->twig->enableStrictVariables();
        $this->twig->addExtension(new SlugifyExtension($slugify));

        /** @var CoreExtension $coreExtension */
        $coreExtension = $this->twig->getExtension(CoreExtension::class);
        $coreExtension->setEscaper(self::ESCAPE_SLUGIFY,
            function ($twig, $string) use ($slugify) {
                $result = rawurlencode($slugify->slugify($string));

                return $result;
            });

        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->salesChannelRepository = $salesChannelRepository;

        $this->router = $router;
        $this->routeName = $routeName;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    protected function getContext(?string $salesChannelId): Context
    {
        if (!$salesChannelId) {
            return Context::createDefaultContext();
        }
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->salesChannelRepository
            ->search(new Criteria([$salesChannelId]), Context::createDefaultContext())
            ->first();
        $options = $salesChannel->jsonSerialize();

        $salesChannelContext = $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannelId,
            $options
        );

        return $salesChannelContext->getContext();
    }
}
