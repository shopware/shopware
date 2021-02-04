<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class ActivateAppCommand extends AbstractAppActivationCommand
{
    private const ACTION = 'activate';

    protected static $defaultName = 'app:activate';

    /**
     * @var AppStateService
     */
    private $appStateService;

    public function __construct(EntityRepositoryInterface $appRepo, AppStateService $appStateService)
    {
        $this->appStateService = $appStateService;

        parent::__construct($appRepo, self::ACTION);
    }

    public function runAction(string $appId, Context $context): void
    {
        $this->appStateService->activateApp($appId, $context);
    }
}
