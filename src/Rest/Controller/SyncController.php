<?php declare(strict_types=1);

namespace Shopware\Rest\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Entity\DefinitionRegistry;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Rest\ApiContext;
use Shopware\Rest\RestController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.api.controller.sync_controller", path="/api/sync")
 */
class SyncController extends RestController
{
    const ACTION_UPSERT = 'upsert';

    /**
     * @var DefinitionRegistry
     */
    protected $registry;

    public function __construct(DefinitionRegistry $registry, ContainerInterface $container)
    {
        $this->registry = $registry;
        $this->container = $container;
    }

    /**
     * @Route("", name="sync.api")
     * @Method({"POST"})
     *
     * @param ApiContext $apiContext
     *
     * @return Response
     */
    public function syncAction(ApiContext $apiContext): Response
    {
        $payload = $apiContext->getPayload();
        $context = $apiContext->getTranslationContext();

        $errors = $result = [];

        foreach ($payload as $operation) {
            $action = $operation['action'];
            $entity = $operation['entity'];

            $definition = $this->registry->get($entity);

            /** @var RepositoryInterface $repository */
            $repository = $this->container->get($definition::getRepositoryClass());

            switch ($action) {
                case self::ACTION_UPSERT:
                    /** @var WrittenEvent $event */
                    $generic = $repository->upsert(
                        [$operation['payload']],
                        $context
                    );

                    foreach ($generic->getEvents() as $event) {
                        $eventDefinition = $event->getDefinition();

                        if (array_key_exists($eventDefinition, $result)) {
                            $result[$eventDefinition]['uuids'] = array_merge(
                                $result[$eventDefinition]['uuids'],
                                $event->getUuids()
                            );
                        } else {
                            $result[$eventDefinition] = [
                                'definition' => $eventDefinition,
                                'uuids' => $event->getUuids(),
                            ];
                        }

                        $errors = array_merge($errors, $event->getErrors());
                    }

                    break;
            }
        }

        $result = array_values($result);

        return $this->createResponse(['data' => $result], $apiContext);
    }
}
