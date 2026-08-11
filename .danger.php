<?php declare(strict_types=1);

use Danger\Config;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\AgenticCommercePluginHint;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\ComposerVersionConstraints;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\DangerConfigChanged;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\DeprecatedChangelogFormat;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\EntityRepositoryInFrontendLayer;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\IgnoredPhpstanErrorsInTouchedFiles;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\InlineRuleInDangerConfig;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\InvalidFileNameCharacters;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\LegacyTestsInSrc;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingIntegrationTestInSplitSuite;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingMigrationTests;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingPackageAttributeInTests;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingReleaseInfo;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingUnitTests;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\PhpstanBaselineGrowth;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\RedisGroupUsage;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\RemovedTwigBlocks;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\RouteSnapshotExtension;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\ShopwareYamlConfigSchemaHint;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\SingleCoversClassInTests;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\SqlHeredocUsage;

// danger runs on its own vendor-bin autoloader (vendor-bin/danger-php), which does not know the
// Shopware namespaces — load the rule classes directly instead
foreach (glob(__DIR__ . '/src/Core/DevOps/StaticAnalyze/Danger/Rules/*.php') ?: [] as $ruleFile) {
    require_once $ruleFile;
}

return (new Config())
    ->useThreadOn(Config::REPORT_LEVEL_WARNING)
    ->useRule(new DangerConfigChanged())
    ->useRule(new InlineRuleInDangerConfig())
    ->useRule(new DeprecatedChangelogFormat())
    ->useRule(new MissingReleaseInfo())
    ->useRule(new IgnoredPhpstanErrorsInTouchedFiles())
    ->useRule(new PhpstanBaselineGrowth())
    ->useRule(new EntityRepositoryInFrontendLayer())
    ->useRule(new ShopwareYamlConfigSchemaHint())
    ->useRule(new AgenticCommercePluginHint())
    ->useRule(new MissingMigrationTests())
    ->useRule(new MissingPackageAttributeInTests())
    ->useRule(new RedisGroupUsage())
    ->useRule(new SingleCoversClassInTests())
    ->useRule(new SqlHeredocUsage())
    ->useRule(new RemovedTwigBlocks())
    ->useRule(new InvalidFileNameCharacters())
    ->useRule(new LegacyTestsInSrc())
    ->useRule(new MissingUnitTests())
    ->useRule(new ComposerVersionConstraints())
    ->useRule(new MissingIntegrationTestInSplitSuite())
    ->useRule(new RouteSnapshotExtension())
;
