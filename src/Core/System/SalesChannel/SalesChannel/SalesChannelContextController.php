<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SalesChannelContextController extends AbstractController
{
    /**
     * @var SalesChannelContextSwitcher
     */
    protected $contextSwitcher;

    public function __construct(SalesChannelContextSwitcher $contextSwitcher)
    {
        $this->contextSwitcher = $contextSwitcher;
    }

    /**
     * @Route("/sales-channel-api/v{version}/context", name="sales-channel-api.context.update", methods={"PATCH"})
     *
     * @throws AddressNotFoundException
     * @throws CustomerNotLoggedInException
     */
    public function update(Request $request, SalesChannelContext $context): JsonResponse
    {
        $payload = $request->request->all();

        $this->contextSwitcher->update($payload, $context);

        return new JsonResponse([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken(),
        ]);
    }
}
