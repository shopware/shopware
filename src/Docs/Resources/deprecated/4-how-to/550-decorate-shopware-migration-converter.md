[titleEn]: <>(Decorating a Shopware Migration Assistant converter)
[metaDescriptionEn]: <>(This HowTo will give an example on decorating a Shopware migration converter of the Migration Connector plugin.)
[hash]: <>(article:how_to_decorate_migration_connector)

## Overview

In this HowTo, you will learn how you can decorate a Shopware migration converter of the [Migration Connector](https://github.com/shopware/SwagMigrationConnector) 
plugin. Here, the decorated converter will modify the converted products and get data out of a `premapping field`.

## Setup

It is required that you already have installed the [Migration Assistant](https://github.com/shopware/SwagMigrationAssistant) plugin
in Shopware 6 and have a running Shopware 5 system running to connect the Migration Assistant via API or local gateway.

## Enrich existing plugin with migration features

Instead of creating a new plugin for the migration, you might want to add migration features to your existing plugin.
Of course, your plugin should then also be installable without the Migration Assistant plugin.
So we have an optional requirement. Have a look at this [HowTo](./590-optional-plugin-requirements.md)
on how to inject the needed migration services only if the Migration Assistant plugin is available.
You could also have a look at the example plugin, to see how the conditional loading is managed in the plugin base class.

## Creating a premapping reader

In this example, the user should be able to map the manufacturer, while no new manufacturer will be created.
You have to create a new premapping reader to achieve this:

```php
<?php declare(strict_types=1);

namespace SwagMigrationExtendConverterExample\Profile\Shopware\Premapping;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\ProductDataSelection;
use SwagMigrationAssistant\Profile\Shopware\Gateway\ShopwareGatewayInterface;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

class ManufacturerReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'swag_manufacturer';

    /**
     * @var EntityRepositoryInterface
     */
    private $manufacturerRepo;

    /**
     * @var GatewayRegistryInterface
     */
    private $gatewayRegistry;

    /**
     * @var string[]
     */
    private $preselectionDictionary;

    /**
     * @var string[]
     */
    private $preselectionSourceNameDictionary;

    public function __construct(
        EntityRepositoryInterface $manufacturerRepo,
        GatewayRegistryInterface $gatewayRegistry
    ) {
        $this->manufacturerRepo = $manufacturerRepo;
        $this->gatewayRegistry = $gatewayRegistry;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    /**
     * Checks whether or not the current profile and DataSelection is supported
     */
    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface
            && in_array(ProductDataSelection::IDENTIFIER, $entityGroupNames, true);
    }

    public function getPremapping(Context $context, MigrationContextInterface $migrationContext): PremappingStruct
    {
        $this->fillConnectionPremappingDictionary($migrationContext);
        $mapping = $this->getMapping($migrationContext);
        $choices = $this->getChoices($context);
        $this->setPreselection($mapping);

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    /**
     * Reads all manufacturers of the source system, looks into connectionPremappingDictionary if a premapping
     * is currently set and returns the filled mapping array
     *
     * @return PremappingEntityStruct[]
     */
    private function getMapping(MigrationContextInterface $migrationContext): array
    {
        /** @var ShopwareGatewayInterface $gateway */
        $gateway = $this->gatewayRegistry->getGateway($migrationContext);

        $preMappingData = $gateway->readTable($migrationContext, 's_articles_supplier');

        $entityData = [];
        foreach ($preMappingData as $data) {
            $this->preselectionSourceNameDictionary[$data['id']] = $data['name'];

            $uuid = '';
            if (isset($this->connectionPremappingDictionary[$data['id']])) {
                $uuid = $this->connectionPremappingDictionary[$data['id']]['destinationUuid'];
            }

            $entityData[] = new PremappingEntityStruct($data['id'], $data['name'], $uuid);
        }

        return $entityData;
    }

    /**
     * Returns all choices of the manufacturer repository
     *
     * @return PremappingChoiceStruct[]
     */
    private function getChoices(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name'));

        /** @var ProductManufacturerEntity[] $manufacturers */
        $manufacturers = $this->manufacturerRepo->search($criteria, $context);

        $choices = [];
        foreach ($manufacturers as $manufacturer) {
            $this->preselectionDictionary[$manufacturer->getName()] = $manufacturer->getId();
            $choices[] = new PremappingChoiceStruct($manufacturer->getId(), $manufacturer->getName());
        }

        return $choices;
    }

    /**
     * Loops through mapping and sets preselection, if uuid is currently not set
     *
     * @param PremappingEntityStruct[] $mapping
     */
    private function setPreselection(array $mapping): void
    {
        foreach ($mapping as $item) {
            if (!isset($this->preselectionSourceNameDictionary[$item->getSourceId()]) || $item->getDestinationUuid() !== '') {
                continue;
            }

            $sourceName = $this->preselectionSourceNameDictionary[$item->getSourceId()];
            $preselectionValue = $this->getPreselectionValue($sourceName);

            if ($preselectionValue !== null) {
                $item->setDestinationUuid($preselectionValue);
            }
        }
    }

    /**
     * Only a simple example on how to implement a preselection
     */
    private function getPreselectionValue(string $sourceName): ?string
    {
        $preselectionValue = null;
        $validPreselection = 'Shopware';
        $choice = 'shopware AG';

        if ($sourceName === $validPreselection && isset($this->preselectionDictionary[$choice])) {
            $preselectionValue = $this->preselectionDictionary[$choice];
        }

        return $preselectionValue;
    }
}
```
 
 The created premapping reader fetches all manufacturers of the source system, gets all manufacturer choices out
 of the Shopware 6 database and does a simple preselection via the manufacturer name. The `getPremapping` function
 returns the whole premapping structure. With this structure, the administration creates a new premapping card and
 creates for each source system manufacturer a selectbox with all Shopware 6 manufacturers as choices.
 For more details have a look at the [premapping concept](./../2-internals/4-plugins/010-shopware-migration-assistant/050-premapping.md)
 
 ## Adding snippets to premapping card
 
 Currently, the premapping card has no snippets at all, so you have to create a new snippet file for the title:
 
 ```json
 {
     "swag-migration": {
         "index": {
             "premappingCard": {
                 "group": {
                     "swag_manufacturer": "Manufacturer"
                 }
             }
         }
     }
 }
 ```
 
 This file has to be located in `Resources\administration\snippet` and registered in `Resources\administration\main.js` of
 the plugin, like this:
 
 ```javascript
import enGBSnippets from './snippet/en-GB.json';

const { Application } = Shopware;
 
Application.addInitializerDecorator('locale', (localeFactory) => {
    localeFactory.extend('en-GB', enGBSnippets);

     return localeFactory;
});
 ```
 Now your new premapping card has a correct title.
 
 ## Decorate the product migration converter
 
 After creating your premapping reader, you have a new premapping card, but this premapping is currently not in use.
 To map the product manufacturers of the source system to your premapping values, you have to decorate one of the Shopware
 product migration converters. In this example only the `Shopware55ProductConverter` is decorated, but if you want to decorate all
 Shopware migration converters, you have to do the same:
 
 ```php
 <?php declare(strict_types=1);
 
 namespace SwagMigrationExtendConverterExample\Profile\Shopware\Converter;
 
 use Shopware\Core\Framework\Context;
 use SwagMigrationAssistant\Migration\Converter\ConverterInterface;
 use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
 use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;
 use SwagMigrationAssistant\Migration\MigrationContextInterface;
 use SwagMigrationExtendConverterExample\Profile\Shopware\Premapping\ManufacturerReader;
 
 class Shopware55DecoratedProductConverter implements ConverterInterface
 {
     /**
      * @var ConverterInterface
      */
     private $originalProductConverter;
 
     /**
      * @var MappingServiceInterface
      */
     private $mappingService;
 
     public function __construct
     (
         ConverterInterface $originalProductConverter,
         MappingServiceInterface $mappingService
     ) {
         $this->originalProductConverter = $originalProductConverter;
         $this->mappingService = $mappingService;
     }
 
     public function supports(MigrationContextInterface $migrationContext): bool
     {
         return $this->originalProductConverter->supports($migrationContext);
     }
     
     public function getSourceIdentifier(array $data): string
     {
         return $this->originalProductConverter->getSourceIdentifier($data);
     }

     public function getMediaUuids(array $converted): ?array
     {
         return $this->originalProductConverter->getMediaUuids($converted);
     }
 
     public function writeMapping(Context $context): void
     {
         $this->originalProductConverter->writeMapping($context);
     }
 
     public function convert(
         array $data,
         Context $context,
         MigrationContextInterface $migrationContext
     ): ConvertStruct
     {
         if (!isset($data['manufacturer']['id'])) {
             return $this->originalProductConverter->convert($data, $context, $migrationContext);
         }
 
         $manufacturerId = $data['manufacturer']['id'];
         unset($data['manufacturer']);
 
         $mapping = $this->mappingService->getMapping(
             $migrationContext->getConnection()->getId(),
             ManufacturerReader::getMappingName(),
             $manufacturerId,
             $context
         );
 
         $convertedStruct = $this->originalProductConverter->convert($data, $context, $migrationContext);
 
         if ($mapping === null) {
             return $convertedStruct;
         }
 
         $converted = $convertedStruct->getConverted();
         $converted['manufacturerId'] = $mapping['entityUuid'];
 
         return new ConvertStruct($converted, $convertedStruct->getUnmapped(), $convertedStruct->getMappingUuid());
     }
 }
 ``` 
 
 Your new decorated product migration converter checks, if a manufacturer is set and searches for the premapping via the `MappingService`.
 If a premapping is found, the migration converter uses the converted value of the original converter, adds the manufacturer uuid and
 returns the new `ConvertStruct`.
 
 In the end you have to register your decorated converter in your `services.xml`:
 
 ```xml
 <service id="SwagMigrationExtendConverterExample\Profile\Shopware\Converter\Shopware55DecoratedProductConverter"
          decorates="SwagMigrationAssistant\Profile\Shopware55\Converter\Shopware55ProductConverter">
    <argument type="service" id="SwagMigrationExtendConverterExample\Profile\Shopware\Converter\Shopware55DecoratedProductConverter.inner"/>
    <argument type="service" id="SwagMigrationAssistant\Migration\Mapping\MappingService"/>
</service>
 ```
 
 Now you're done. You have already decorated your first Shopware migration converter.
 
 ## Source
 
 There's a GitHub repository available, containing a full example source.
 Check it out [here](https://github.com/shopware/swag-docs-decorate-shopware-migration-converter).
