<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal only for use by the app-system
 */
#[AsCommand(
    name: 'app:uninstall',
    description: 'Uninstalls an app',
)]
#[Package('framework')]
class UninstallAppCommand extends Command
{
    public function __construct(
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly AppStorage $appStorage
    ) {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'The name of the app')]
        string $name,
        #[Option(description: 'Keep user data of the app')]
        bool $keepUserData = false,
        #[Option(description: 'Use this option to skip recompiling of all themes')]
        bool $skipThemeCompile = false,
    ): int {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createCLIContext();
        if ($skipThemeCompile) {
            // Storefront's ThemeLifecycleHandler reads this context state to skip theme compilation
            $context->addState(AbstractAppLifecycle::STATE_SKIP_THEME_COMPILATION);
        }

        $app = $this->appStorage->findByName($name, $context);

        if (!$app) {
            $io->error(\sprintf('No app with name "%s" installed.', $name));

            return self::FAILURE;
        }

        $this->appLifecycle->uninstall(
            $app->getName(),
            [
                'id' => $app->getId(),
                'roleId' => $app->getAclRoleId(),
            ],
            $context,
            $keepUserData
        );

        $io->success('App uninstalled successfully.');

        return self::SUCCESS;
    }
}
