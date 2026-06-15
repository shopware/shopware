<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\Service\TranslationMetadataLoader;
use Shopware\Core\System\Snippet\SnippetPatterns;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Locales;

/**
 * @internal
 */
#[AsCommand(
    name: 'translation:list',
    description: 'Lists all locales that are configured for translation:install / translation:update.',
)]
#[Package('discovery')]
class ListTranslationsCommand extends Command
{
    public function __construct(
        private readonly TranslationConfig $config,
        private readonly TranslationMetadataLoader $metadataLoader,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $installed = $this->metadataLoader->getLocalMetadata();

        $rows = [];
        foreach ($this->config->languages as $language) {
            $entry = $installed->get($language->locale);

            $rows[] = [
                $language->locale,
                $language->name,
                $this->getEnglishName($language->locale),
                $this->formatLastUpdate($entry),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        $table = new Table($output);
        $table->setStyle('box-double');
        $table->setHeaders(['Locale', 'Name', 'Name (English)', 'Last update']);
        $table->setRows($rows);
        $table->render();

        $output->writeln(\sprintf('<info>%d locales configured.</info>', \count($rows)));

        return self::SUCCESS;
    }

    private function getEnglishName(string $locale): string
    {
        if (\array_key_exists($locale, SnippetPatterns::ALLOWED_PSEUDO_LOCALES)) {
            return SnippetPatterns::ALLOWED_PSEUDO_LOCALES[$locale];
        }

        try {
            return Locales::getName(str_replace('-', '_', $locale), 'en');
        } catch (MissingResourceException) {
            return '';
        }
    }

    private function formatLastUpdate(?MetadataEntry $entry): string
    {
        if ($entry === null) {
            return '—';
        }

        return $entry->updatedAt->format('Y-m-d H:i');
    }
}
