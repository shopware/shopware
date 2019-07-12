<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Command;

use Shopware\Core\Framework\Context;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ThemeRefreshCommand extends Command
{
    /**
     * @var ThemeLifecycleService
     */
    private $themeLifecycleService;

    /**
     * @var Context
     */
    private $context;

    public function __construct(ThemeLifecycleService $themeLifecycleService)
    {
        parent::__construct('theme:refresh');

        $this->themeLifecycleService = $themeLifecycleService;
        $this->context = Context::createDefaultContext();
    }

    protected function configure()
    {
        $this->setName('theme:refresh');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->themeLifecycleService->refreshThemes($this->context);
    }
}
