<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\TranslationCommandHelper;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[AsCommand(
    name: 'translation:download',
    description: 'Downloads all configured translations without creating languages or snippet sets.',
)]
class DownloadTranslationCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractTranslationLoader $translationLoader,
        private readonly TranslationConfig $config,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        TranslationCommandHelper::executeLoadWithProgressBar(
            $this->config->locales,
            $output,
            fn (string $locale) => $this->translationLoader->download($locale),
        );

        $output->write(\PHP_EOL);

        return self::SUCCESS;
    }
}
