<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\TranslationCommandHelper;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[AsCommand(
    name: 'translation:update',
    description: 'Updates all installed translations from the translations GitHub repository'
)]
class UpdateTranslationCommand extends Command
{
    public function __construct(
        private readonly AbstractTranslationLoader $translationLoader,
        private readonly TranslationMetadataStore $metadataStore,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $metadata = $this->metadataStore->getUpdatedLocalMetadata();
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
            'Updating translations',
            fn (string $locale) => $this->translationLoader->load($locale, $context),
        );

        $output->write(\PHP_EOL);

        TranslationCommandHelper::handleSavingMetadataCLIOutput(
            fn () => $this->metadataStore->save($metadata),
            $output
        );

        return self::SUCCESS;
    }
}
