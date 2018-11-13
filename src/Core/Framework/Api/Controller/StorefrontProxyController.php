<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelStruct;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Annotation\Route;

class StorefrontProxyController extends AbstractController
{
    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var RepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var CheckoutContextService
     */
    private $contextService;

    /**
     * @var SalesChannelRequestContextResolver
     */
    private $requestContextResolver;

    public function __construct(
        Kernel $kernel,
        RepositoryInterface $salesChannelRepository,
        CheckoutContextService $contextService,
        SalesChannelRequestContextResolver $requestContextResolver)
    {
        $this->kernel = $kernel;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->contextService = $contextService;
        $this->requestContextResolver = $requestContextResolver;
    }

    /**
     * @Route("/api/v{version}/proxy/storefront-api/{salesChannelId}/{_path}", name="api.proxy.storefront", requirements={"_path" = ".*"})
     *
     * @throws InvalidSalesChannelIdException
     */
    public function proxy(string $_path, string $salesChannelId, Request $request, Context $context)
    {
        /** @var SalesChannelStruct|null $salesChannel */
        $salesChannel = $this->salesChannelRepository->read(new ReadCriteria([$salesChannelId]), $context)->get($salesChannelId);

        if (!$salesChannel) {
            throw new InvalidSalesChannelIdException($salesChannelId);
        }

        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        if (!$contextToken) {
            $contextToken = Uuid::uuid4()->getHex();
        }

        $server = array_merge($request->server->all(), ['REQUEST_URI' => '/storefront-api/' . $_path]);
        $cloned = $request->duplicate(null, null, [], null, null, $server);
        $cloned->headers->set(PlatformRequest::HEADER_ACCESS_KEY, $salesChannel->getAccessKey());
        $cloned->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        $cloned->headers->set(PlatformRequest::HEADER_TENANT_ID, $context->getTenantId());
        $cloned->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $salesChannel->getAccessKey());

        $this->requestContextResolver->handleCheckoutContext(
            $request,
            $cloned,
            $context->getTenantId(),
            $salesChannelId,
            $contextToken
        );

        return $this->kernel->handle($cloned, HttpKernelInterface::SUB_REQUEST);
    }
}
