<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

class SalesChannelProxyController extends AbstractController
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var SalesChannelRequestContextResolver
     */
    private $requestContextResolver;

    public function __construct(
        KernelInterface $kernel,
        EntityRepositoryInterface $salesChannelRepository,
        SalesChannelRequestContextResolver $requestContextResolver
    ) {
        $this->kernel = $kernel;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->requestContextResolver = $requestContextResolver;
    }

    /**
     * @Route("/api/v{version}/_proxy/sales-channel-api/{salesChannelId}/{_path}", name="api.proxy.sales-channel", requirements={"_path" = ".*"})
     *
     * @throws InvalidSalesChannelIdException
     */
    public function proxy(string $_path, string $salesChannelId, Request $request, Context $context): Response
    {
        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), $context)->get($salesChannelId);

        if (!$salesChannel) {
            throw new InvalidSalesChannelIdException($salesChannelId);
        }

        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        if (!$contextToken) {
            $contextToken = Random::getAlphanumericString(32);
        }

        $server = array_merge($request->server->all(), ['REQUEST_URI' => '/sales-channel-api/' . $_path]);
        $cloned = $request->duplicate(null, null, [], null, null, $server);
        $cloned->headers->set(PlatformRequest::HEADER_ACCESS_KEY, $salesChannel->getAccessKey());
        $cloned->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        $cloned->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $salesChannel->getAccessKey());

        $this->requestContextResolver->handleSalesChannelContext(
            $request,
            $cloned,
            $salesChannelId,
            $contextToken
        );

        return $this->kernel->handle($cloned, HttpKernelInterface::SUB_REQUEST);
    }
}
