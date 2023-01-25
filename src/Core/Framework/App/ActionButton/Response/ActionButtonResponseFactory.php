<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ActionButtonResponseFactory
{
    /**
     * @param ActionButtonResponseFactoryInterface[] $factories
     */
    public function __construct(private readonly iterable $factories)
    {
    }

    public function createFromResponse(AppAction $action, string $actionType, array $payload, Context $context): ActionButtonResponse
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($actionType)) {
                return $factory->create($action, $payload, $context);
            }
        }

        throw new ActionProcessException($action->getActionId(), sprintf('No factory found for action type "%s"', $actionType));
    }
}
