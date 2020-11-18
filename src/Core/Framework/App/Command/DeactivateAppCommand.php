<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class DeactivateAppCommand extends AbstractAppActivationCommand
{
    private const ACTION = 'deactivate';

    protected static $defaultName = 'app:deactivate';

    /**
     * @var AppStateService
     */
    private $appStateService;

    public function __construct(EntityRepositoryInterface $appRepo, AppStateService $appStateService)
    {
        parent::__construct($appRepo, self::ACTION);
        $this->appStateService = $appStateService;
    }

    public function runAction(string $appId, Context $context): void
    {
        $this->appStateService->deactivateApp($appId, $context);
    }
}
