<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ScheduledTask\Api;

use Shopware\Core\Framework\ScheduledTask\Scheduler\TaskScheduler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

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
     * @Route("/api/v{version}/_action/scheduled-task/run", name="api.action.scheduled-task.run", methods={"POST"})
     */
    public function runScheduledTasks(): JsonResponse
    {
        $this->taskScheduler->queueScheduledTasks();

        return $this->json(['message' => 'Success']);
    }

    /**
     * @Route("/api/v{version}/_action/scheduled-task/min-run-interval", name="api.action.scheduled-task.min-run-interval", methods={"GET"})
     */
    public function getMinRunInterval(): JsonResponse
    {
        return $this->json(['minRunInterval' => $this->taskScheduler->getMinRunInterval()]);
    }
}
