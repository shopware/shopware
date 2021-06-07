<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Api;

use OpenApi\Annotations as OA;
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
     * @OA\Post(
     *     path="/_action/scheduled-task/run",
     *     summary="Run scheduled tasks.",
     *     description="Starts the scheduled task worker to handle the next scheduled tasks.",
     *     operationId="runScheduledTasks",
     *     tags={"Admin API", "System Operations"},
     *     @OA\Response(
     *         response="200",
     *         description="Returns a success message indicating a successful run.",
     *         @OA\JsonContent(
     *               @OA\Property(
     *                  property="message",
     *                  description="Success message",
     *                  type="string"
     *              )
     *         )
     *     )
     * )
     * @Route("/api/_action/scheduled-task/run", name="api.action.scheduled-task.run", methods={"POST"})
     */
    public function runScheduledTasks(): JsonResponse
    {
        $this->taskScheduler->queueScheduledTasks();

        return $this->json(['message' => 'Success']);
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Get(
     *     path="/_action/scheduled-task/min-run-interval",
     *     summary="Get the minimum schedules task interval",
     *     description="Fetches the smallest interval that a scheduled task uses.",
     *     operationId="getMinRunInterval",
     *     tags={"Admin API", "System Operations"},
     *     @OA\Response(
     *         response="200",
     *         description="Returns the minimum interval.",
     *         @OA\JsonContent(
     *               @OA\Property(
     *                  property="minRunInterval",
     *                  description="Minimal interval in seconds.",
     *                  type="string"
     *              )
     *         )
     *     )
     * )
     * @Route("/api/_action/scheduled-task/min-run-interval", name="api.action.scheduled-task.min-run-interval", methods={"GET"})
     */
    public function getMinRunInterval(): JsonResponse
    {
        return $this->json(['minRunInterval' => $this->taskScheduler->getMinRunInterval()]);
    }
}
