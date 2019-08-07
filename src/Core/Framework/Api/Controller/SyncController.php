<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

/**
 * @RouteScope(scopes={"api"})
 */
class SyncController extends AbstractController
{
    public const ACTION_UPSERT = 'upsert';
    public const ACTION_DELETE = 'delete';

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(DefinitionInstanceRegistry $definitionRegistry, Serializer $serializer)
    {
        $this->definitionRegistry = $definitionRegistry;
        $this->serializer = $serializer;
    }

    /**
     * Starts a sync process for the list of provided actions.
     * This can be inserts, upserts, updates and deletes on different entities.
     * To continue upcoming actions on errors, please provide a "fail-on-error" header with value FALSE.
     *
     * @Route("/api/v{version}/_action/sync", name="api.action.sync", methods={"POST"})
     *
     * @throws \Throwable
     */
    public function sync(Request $request, Context $context): JsonResponse
    {
        // depending on the request header setting, we either
        // fail immediately or add any unexpected errors to our exception list
        /** @var bool $failOnError */
        $failOnError = filter_var($request->headers->get('fail-on-error', 'true'), FILTER_VALIDATE_BOOLEAN);

        $errors = $result = $exceptions = [];

        $payload = $this->serializer->decode($request->getContent(), 'json');

        foreach ($payload as $operation) {
            $action = $operation['action'];
            $entity = $operation['entity'];

            $repository = $this->definitionRegistry->getRepository($entity);

            switch ($action) {
                case self::ACTION_DELETE:
                    $generic = $repository->delete($operation['payload'], $context);

                    $errors = array_merge($errors, $generic->getErrors());

                    break;

                case self::ACTION_UPSERT:
                    try {
                        $generic = $repository->upsert(
                            $operation['payload'],
                            $context
                        );

                        /** @var EntityWrittenEvent $event */
                        foreach ($generic->getEvents() as $event) {
                            $eventDefinition = $event->getDefinition();

                            if (array_key_exists($eventDefinition->getClass(), $result)) {
                                $result[$eventDefinition->getClass()]['ids'] = array_merge(
                                    $result[$eventDefinition->getClass()]['ids'],
                                    $event->getIds()
                                );
                            } else {
                                $result[$eventDefinition->getClass()] = [
                                    'definition' => $eventDefinition,
                                    'ids' => $event->getIds(),
                                ];
                            }

                            $errors = array_merge($errors, $event->getErrors());
                        }
                    } catch (WriteException $exception) {
                        $errors = array_merge($errors, iterator_to_array($exception->getErrors()));
                    } catch (\Throwable $throwable) {
                        // we have an unexpected error,
                        // let's see if we should fail or not
                        if ($failOnError) {
                            throw $throwable;
                        }
                        // otherwise, add it to our exception stack
                        $exceptions[$action][$entity][] = $throwable->getMessage();
                    }

                    break;
            }
        }

        $result = array_values($result);

        $response = [
            'data' => $result,
            'errors' => $errors,
            'exceptions' => $exceptions,
        ];

        return new JsonResponse($response);
    }
}
