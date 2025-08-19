<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\SalesChannel;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('fundamentals@discovery')]
class LanguageRoute extends AbstractLanguageRoute
{
    final public const ALL_TAG = 'language-route';

    /**
     * @internal
     *
     * @param SalesChannelRepository<LanguageCollection> $repository
     */
    public function __construct(
        private readonly SalesChannelRepository $repository,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'language-route-' . $id;
    }

    public function getDecorated(): AbstractLanguageRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/language', name: 'store-api.language', methods: ['GET', 'POST'], defaults: ['_entity' => 'language'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): LanguageRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($context->getSalesChannelId()), self::ALL_TAG);

        $criteria->addAssociation('translationCode');

        return new LanguageRouteResponse(
            $this->repository->search($criteria, $context)
        );
    }
}
