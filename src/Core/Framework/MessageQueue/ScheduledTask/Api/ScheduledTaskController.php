<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Api;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ScheduledTaskController extends AbstractController
{
    /**
     * @var TaskScheduler
     */
    private $taskScheduler;

    public function __construct(TaskScheduler $taskScheduler)
    {
        $this->taskScheduler = $taskScheduler;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/v{version}/_action/scheduled-task/run", name="api.action.scheduled-task.run", methods={"POST"})
     */
    public function runScheduledTasks(): JsonResponse
    {
        $this->taskScheduler->queueScheduledTasks();

        return $this->json(['message' => 'Success']);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/v{version}/_action/scheduled-task/min-run-interval", name="api.action.scheduled-task.min-run-interval", methods={"GET"})
     */
    public function getMinRunInterval(): JsonResponse
    {
        return $this->json(['minRunInterval' => $this->taskScheduler->getMinRunInterval()]);
    }
}
