[titleEn]: <>(Number Range)
[hash]: <>(article:number-range)

Shopware 6 integrates a configurable number range generator and manager. Numberranges are defined unique identifiers for specific entities.

## Components

### NumberRangeValueGenerator

The `NumberRangeValueGenerator` is used to generate a unique identifier for a given entity with a given configuration.

The configuration will be provided in the `administration` where you can provide a pattern for a specific entity in a specific sales channel.

You can reserve a new value for a number range by calling the route `/api/v3/_action/number-range/reserve/{entity}/{salesChannelId}` with the name of the entity like `product` or `order` and, for sales channel dependent number ranges, also the salesChanelId 

In-Code reservation of a new value for a number range can be done by using the `NumberRangeValueGenerator` method `getValue(string $definition, Context $context, ?string $salesChannelId)` directly. 

#### Patterns

Build-In patterns are the following:

`increment`(_'n'_)
   : Generates a consecutive number, the value to start with can be defined in the configuration

`date`(_'date'_,_'date_ymd'_)
   : Generates the date by time of generation. The standard format is 'y-m-d'. The format can be overwritten by passing the format as part of the pattern. The pattern `date_ymd` generates a date in the Format 190231. This pattern accepts a [PHP Dateformat-String](http://php.net/manual/en/function.date.php#refsect1-function.date-parameters)  

#### Pattern example

`Order{date_dmy}_{n}` will generate a value like _Order310219_5489

 
### ValueGeneratorPattern

The ValueGeneratorPattern is a resolver for a part of the whole pattern configured for a given number range.
The build-in patterns mentioned above have a corresponding pattern resolver which is responsible for resolving the pattern to the correct value.

A ValueGeneratorPattern can easily be added to extend the possibilities for specific requirements.

You only need to derive a class from `ValueGeneratorPattern` and implement your custom rules to the `resolve`-method. 

### IncrementConnector

The increment pattern is somewhat special because it needs to communicate with a persistence layer in some way.
The IncrementConnector allows you to overwrite the connection interface for the increment pattern to switch to a more perfomant solution for this sepcific task.
If you want to overwrite the IncrementConnector you have to implement the IncrementConnectorInterface in your new connector class and register your new class with the id of the interface.

```xml
<service class="MyNewIncrementConnector"
                 id="Shopware\Core\System\NumberRange\ValueGenerator\IncrementConnectorInterface">
            <tag name="shopware.value_generator_connector"/>
        </service>
