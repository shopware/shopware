<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ActionButton\Response;

use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
interface ActionButtonResponseFactoryInterface
{
    public function supports(string $actionType): bool;

    public function create(AppAction $action, array $payload, Context $context): ActionButtonResponse;
}
