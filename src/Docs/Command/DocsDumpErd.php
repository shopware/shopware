<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\DeliveryTime\Aggregate\DeliveryTimeTranslation\DeliveryTimeTranslationDefinition;
use Shopware\Core\Content\DeliveryTime\DeliveryTimeDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel\MailTemplateSalesChannelDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Content\NewsletterReceiver\NewsletterReceiverDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\Store\StoreSettingsDefinition;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\Version\VersionDefinition;
use Shopware\Docs\Inspection\ArrayWriter;
use Shopware\Docs\Inspection\ErdDefinition;
use Shopware\Docs\Inspection\ErdGenerator;
use Shopware\Docs\Inspection\MarkdownErdDumper;
use Shopware\Docs\Inspection\PlantUmlErdDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class DocsDumpErd extends Command
{
    private $ignoredDefinitions = [
        VersionCommitDataDefinition::class,
        VersionCommitDefinition::class,
        VersionDefinition::class,

        CmsBlockDefinition::class,
        CmsPageDefinition::class,
        CmsSlotDefinition::class,
        CmsPageTranslationDefinition::class,
        CmsSlotTranslationDefinition::class,

        MailHeaderFooterTranslationDefinition::class,
        MailHeaderFooterDefinition::class,
        MailTemplateDefinition::class,
        MailTemplateSalesChannelDefinition::class,
        MailTemplateTranslationDefinition::class,
        MailTemplateDefinition::class,
        MailTemplateMediaDefinition::class,

        PromotionDefinition::class,
        PromotionSalesChannelDefinition::class,
        PromotionDiscountDefinition::class,

        StoreSettingsDefinition::class,

        NewsletterReceiverDefinition::class,

        DeliveryTimeDefinition::class,
        DeliveryTimeTranslationDefinition::class,
    ];

    /**
     * @var DefinitionRegistry
     */
    private $registry;

    /**
     * @var ErdGenerator
     */
    private $erdGenerator;

    public function __construct(
        DefinitionRegistry $registry,
        ErdGenerator $erdGenerator
    ) {
        parent::__construct();
        $this->registry = $registry;
        $this->erdGenerator = $erdGenerator;
    }

    protected function configure(): void
    {
        $this
            ->setName('docs:dump-erd')
            ->setDescription('Dump an entity relationship diagram');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $descriptionsShort = new ArrayWriter(__DIR__ . '/../Resources/erd-short-description.php');
        $descriptionsLong = new ArrayWriter(__DIR__ . '/../Resources/erd-long-description.php');
        $destPath = __DIR__ . '/../_new/2-internals/1-core/10-erd';

        $fs = new Filesystem();
        $fs->remove(glob($destPath . '/erd-*'));
        $fs->remove($destPath . '/_puml');
        $fs->mkdir($destPath . '/_puml');

        $definitions = $this->loadDefinitions();
        $modules = $this->sortDefinitionsIntoModules($definitions);

        $this->updateTranslations($definitions, $descriptionsLong, $descriptionsShort, $modules);

        $io->listing(array_keys($modules));

        $this->generateModuleErd($modules, $descriptionsShort, $destPath, $descriptionsLong);
        $this->generateGlobalErd($modules, $descriptionsShort, $destPath, $definitions);
    }

    protected function updateTranslations(array $definitions, ArrayWriter $descriptionsLong, ArrayWriter $descriptionsShort, array $modules): void
    {
        foreach ($definitions as $definition) {
            if ($definition->isMapping()) {
                $descriptionsLong->set($definition->toClassName(), '');
                $descriptionsShort->set($definition->toClassName(), 'M:N Mapping');
                continue;
            }

            if ($definition->isTranslation()) {
                $descriptionsLong->set($definition->toClassName(), '');
                $descriptionsShort->set($definition->toClassName(), 'Translations');
                continue;
            }

            $descriptionsShort->ensure($definition->toClassName());
            $descriptionsLong->ensure($definition->toClassName());
        }

        foreach (array_keys($modules) as $moduleName) {
            $descriptionsLong->ensure($moduleName);
            $descriptionsShort->ensure($moduleName);
        }

        $descriptionsShort->dump();
        $descriptionsLong->dump(true);
    }

    protected function generateModuleErd(array $modules, ArrayWriter $descriptionsShort, string $destPath, ArrayWriter $descriptionsLong): void
    {
        /*
         * @var ErdDefinition[]
         */
        foreach ($modules as $moduleName => $moduleDefinition) {
            $dump = $this->erdGenerator->generateFromDefinitions($moduleDefinition, new PlantUmlErdDumper(), $descriptionsShort);
            file_put_contents(
                $destPath . '/_puml/erd-' . $this->toFileName($moduleName) . '.puml',
                $dump
            );

            $dump = $this->erdGenerator->generateFromDefinitions($moduleDefinition, new MarkdownErdDumper(
                $descriptionsShort->get($moduleName),
                $descriptionsLong->get($moduleName),
                'dist/erd-' . $this->toFileName($moduleName) . '.png'
            ), $descriptionsLong);
            file_put_contents(
                $destPath . '/erd-' . $this->toFileName($moduleName) . '.md',
                $dump
            );
        }
    }

    private function toFileName($moduleName): string
    {
        return strtolower(str_replace('\\', '-', $moduleName));
    }

    /**
     * @param ErdDefinition[] $definitions
     */
    private function sortDefinitionsIntoModules(array $definitions): array
    {
        $modules = [];

        foreach ($definitions as $definition) {
            $moduleName = $definition->toModuleName();

            if (!isset($modules[$moduleName])) {
                $modules[$moduleName] = [];
            }

            $modules[$moduleName][] = $definition;
        }

        return $modules;
    }

    /**
     * @return ErdDefinition[]
     */
    private function loadDefinitions(): array
    {
        $definitions = $this->registry->getDefinitions();

        $definitions = array_filter($definitions, function (string $definition) {
            return !in_array($definition, $this->ignoredDefinitions, true);
        });

        return array_map(function (string $definition) {
            return new ErdDefinition($definition);
        }, $definitions);
    }

    private function generateGlobalErd(array $modules, ArrayWriter $descriptionsShort, string $destPath, array $definitions): void
    {
        $dump = $this->erdGenerator->generateFromModules($modules, new PlantUmlErdDumper(), $descriptionsShort);
        file_put_contents(
            $destPath . '/_puml/erd-overview.puml',
            $dump
        );

        $dump = $this->erdGenerator->generateFromDefinitions($definitions, new PlantUmlErdDumper(), $descriptionsShort);
        file_put_contents(
            $destPath . '/_puml/erd-all.puml',
            $dump
        );
    }
}
