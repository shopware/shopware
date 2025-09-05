<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\TranslationCommandHelper;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(name: 'translation:update', description: 'Updates translations from the translations repository for all installed translations')]
#[Package('discovery')]
class UpdateTranslationCommand extends Command
{
    public function __construct(
        private readonly TranslationLoader $translationLoader,
        private readonly TranslationMetadataLoader $metadataLoader,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $metadata = $this->metadataLoader->getUpdatedLocalMetadata();
        } catch (\Throwable $e) {
            $output->writeln(\sprintf('<error>An error occurred while fetching metadata: "%s"</error>', $e->getMessage()));

            return self::FAILURE;
        }

        $localesRequiringUpdate = $metadata->getLocalesRequiringUpdate();
        if ($localesRequiringUpdate === []) {
            $output->writeln('All translations are already up to date.');

            return self::SUCCESS;
        }

        $context = Context::createCLIContext();

        TranslationCommandHelper::executeLoadWithProgressBar(
            $localesRequiringUpdate,
            $output,
            fn (string $locale) => $this->translationLoader->load($locale, $context),
        );

        $output->write(\PHP_EOL);

        TranslationCommandHelper::handleSavingMetadataCLIOutput(
            fn () => $this->metadataLoader->save($metadata),
            $output
        );

        return self::SUCCESS;
    }
}
