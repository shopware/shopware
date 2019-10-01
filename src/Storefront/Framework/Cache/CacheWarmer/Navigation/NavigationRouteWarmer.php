<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer\Navigation;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmer;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

class NavigationRouteWarmer extends CacheRouteWarmer
{
    /**
     * @var RequestTransformerInterface
     */
    private $requestTransformer;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var CategoryDefinition
     */
    private $definition;

    public function __construct(
        RequestTransformerInterface $requestTransformer,
        IteratorFactory $iteratorFactory,
        KernelInterface $kernel,
        RouterInterface $router,
        CategoryDefinition $definition
    ) {
        $this->requestTransformer = $requestTransformer;
        $this->iteratorFactory = $iteratorFactory;
        $this->kernel = $kernel;
        $this->router = $router;
        $this->definition = $definition;
    }

    public function createMessage(SalesChannelDomainEntity $domain, ?array $offset): ?WarmUpMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->definition, $offset);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        return new NavigationRouteMessage($domain->getUrl(), $ids, $iterator->getOffset());
    }

    public function handle($message): void
    {
        if (!$message instanceof NavigationRouteMessage) {
            return;
        }

        if (empty($message->getIds())) {
            return;
        }

        $kernel = $this->createHttpCacheKernel($this->kernel);

        foreach ($message->getIds() as $id) {
            $url = rtrim($message->getDomain(), '/') . $this->router->generate('frontend.navigation.page', ['navigationId' => $id]);
            $request = $this->requestTransformer->transform(Request::create($url));
            $kernel->handle($request);
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [NavigationRouteMessage::class];
    }
}
