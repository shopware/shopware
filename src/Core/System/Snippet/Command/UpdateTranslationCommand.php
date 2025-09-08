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
#[AsCommand(name: 'translation:update', description: 'Updates installed translations from the translations repository')]
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
            TranslationCommandHelper::printMetadataLoadingFailed($output, $e);

            return self::FAILURE;
        }

        $localesRequiringUpdate = $metadata->getLocalesRequiringUpdate();
        if ($localesRequiringUpdate === []) {
            TranslationCommandHelper::printNoTranslationsToUpdate($output);

            return self::SUCCESS;
        }

        $localesDiff = array_diff($metadata->getKeys(), $localesRequiringUpdate);
        if ($localesDiff !== []) {
            TranslationCommandHelper::printSkippedLocales($output, $localesDiff);
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
