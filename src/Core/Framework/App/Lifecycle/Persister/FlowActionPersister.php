<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

/**
 * @internal
 */
class FlowActionPersister
{
    private EntityRepositoryInterface $flowActionsRepository;

    private AbstractAppLoader $appLoader;

    public function __construct(
        EntityRepositoryInterface $flowActionsRepository,
        AbstractAppLoader $appLoader
    ) {
        $this->flowActionsRepository = $flowActionsRepository;
        $this->appLoader = $appLoader;
    }

    public function updateActions(FlowAction $flowAction, string $appId, Context $context, string $defaultLocale): void
    {
        $flowActions = $flowAction->getActions();
        if ($flowActions === null) {
            return;
        }

        $data = [];
        foreach ($flowActions->getActions() as $action) {
            $payload = array_merge([
                'appId' => $appId,
                'iconRaw' => $this->appLoader->getFlowActionIcon($action->getMeta()->getIcon(), $flowAction),
            ], $action->toArray($defaultLocale));
            $data[] = $payload;
        }

        if (!empty($data)) {
            $this->flowActionsRepository->create($data, $context);
        }
    }
}
