<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Api;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package system-settings
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class ScheduledTaskController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly TaskScheduler $taskScheduler)
    {
    }

    /**
     * @Since("6.0.0.0")
     */
    #[Route(path: '/api/_action/scheduled-task/run', name: 'api.action.scheduled-task.run', methods: ['POST'])]
    public function runScheduledTasks(): JsonResponse
    {
        $this->taskScheduler->queueScheduledTasks();

        return $this->json(['message' => 'Success']);
    }

    /**
     * @Since("6.0.0.0")
     */
    #[Route(path: '/api/_action/scheduled-task/min-run-interval', name: 'api.action.scheduled-task.min-run-interval', methods: ['GET'])]
    public function getMinRunInterval(): JsonResponse
    {
        return $this->json(['minRunInterval' => $this->taskScheduler->getMinRunInterval()]);
    }
}
