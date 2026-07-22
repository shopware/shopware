<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
#[AsCommand(
    name: 'app:deactivate',
    description: 'Deactivates an app',
)]
class DeactivateAppCommand extends AbstractAppActivationCommand
{
    private const ACTION = 'deactivate';

    public function __construct(
        AppStorage $appStorage,
        private readonly AbstractAppLifecycle $appLifecycle
    ) {
        parent::__construct($appStorage, self::ACTION);
    }

    public function runAction(string $appId, Context $context): void
    {
        $this->appLifecycle->deactivate($appId, $context);
    }
}
