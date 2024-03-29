<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Dashboard\OrderAmountService;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['administration']])]
#[Package('administration')]
class DashboardController extends AbstractController
{
    public function __construct(private readonly OrderAmountService $orderAmountService)
    {
    }

    #[Route(path: '/api/_admin/dashboard/order-amount/{since}', name: 'api.admin.dashboard.order-amount', defaults: ['_routeScope' => ['administration']], methods: ['GET'])]
    public function orderAmount(string $since, Request $request): JsonResponse
    {
        $paid = $request->query->getBoolean('paid', true);

        $timezone = $request->query->get('timezone', 'UTC');

        $amount = $this->orderAmountService->load($since, $paid, $timezone);

        return new JsonResponse(['statistic' => $amount]);
    }
}
