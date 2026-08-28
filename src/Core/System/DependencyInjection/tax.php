<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationDefinition;
use Shopware\Core\System\Tax\Api\TaxRateFkResolver;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRangeRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRuleTypeFilter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(TaxDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(TaxRuleDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(TaxRuleTypeDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(TaxRuleTypeTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(EntireCountryRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(IndividualStatesRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(ZipCodeRangeRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(ZipCodeRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(TaxRateFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.sync.fk_resolver');
};
