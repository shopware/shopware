<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
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
     * @throws InconsistentCriteriaIdsException
     */
    public function proxy(string $_path, string $salesChannelId, Request $request, Context $context): Response
    {
        $salesChannel = $this->fetchSalesChannel($salesChannelId, $context);

        $salesChannelApiRequest = $this->setUpSalesChannelApiRequest($_path, $salesChannelId, $request, $salesChannel);

        return $this->wrapInSalesChannelApiRoute($salesChannelApiRequest, function () use ($salesChannelApiRequest): Response {
            return $this->kernel->handle($salesChannelApiRequest, HttpKernelInterface::SUB_REQUEST);
        });
    }

    private function wrapInSalesChannelApiRoute(Request $request, callable $call): Response
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->get('request_stack');

        $requestStackBackup = $this->clearRequestStackWithBackup($requestStack);
        $requestStack->push($request);

        try {
            return $call();
        } finally {
            $this->restoreRequestStack($requestStack, $requestStackBackup);
        }
    }

    private function setUpSalesChannelApiRequest(string $path, string $salesChannelId, Request $request, SalesChannelEntity $salesChannel): Request
    {
        $contextToken = $this->getContextToken($request);

        $server = array_merge($request->server->all(), ['REQUEST_URI' => '/sales-channel-api/' . $path]);
        $subrequest = $request->duplicate(null, null, [], null, null, $server);

        $subrequest->headers->set(PlatformRequest::HEADER_ACCESS_KEY, $salesChannel->getAccessKey());
        $subrequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $salesChannel->getAccessKey());

        $this->requestContextResolver->handleSalesChannelContext(
            $subrequest,
            $salesChannelId,
            $contextToken
        );

        return $subrequest;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidSalesChannelIdException
     */
    private function fetchSalesChannel(string $salesChannelId, Context $context): SalesChannelEntity
    {
        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), $context)->get($salesChannelId);

        if ($salesChannel === null) {
            throw new InvalidSalesChannelIdException($salesChannelId);
        }

        return $salesChannel;
    }

    private function getContextToken(Request $request): string
    {
        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        if (!$contextToken) {
            $contextToken = Random::getAlphanumericString(32);
        }

        return (string) $contextToken;
    }

    private function clearRequestStackWithBackup(RequestStack $requestStack): array
    {
        $requestStackBackup = [];

        while ($requestStack->getMasterRequest()) {
            $requestStackBackup[] = $requestStack->pop();
        }

        return $requestStackBackup;
    }

    private function restoreRequestStack(RequestStack $requestStack, array $requestStackBackup): void
    {
        $this->clearRequestStackWithBackup($requestStack);

        foreach ($requestStackBackup as $backedUpRequest) {
            $requestStack->push($backedUpRequest);
        }
    }
}
