[titleEn]: <>(Logging)
[hash]: <>(article:migration_logging)

Logging is essential for anyone using the Shopware Migration Assistant. In case of failure it enables users to find out
why part of their data might be missing. Most of the logging takes place in the `Converter` classes, each time they detect
missing required values. Also every exception will create a log entry automatically.

We use `LogEntry` objects for our logging, so it's easier to group logs / errors of the same type and get the corresponding amount.
Here is an example of how the logging works in the `CustomerConverter`:
```php
<?php declare(strict_types=1);

abstract class CustomerConverter extends ShopwareConverter
{
    /* ... */
    
    public function convert(
            array $data,
            Context $context,
            MigrationContextInterface $migrationContext
        ): ConvertStruct
    {
        $this->generateChecksum($data);
        $oldData = $data;
        $this->runId = $migrationContext->getRunUuid();

        $fields = $this->checkForEmptyRequiredDataFields($data, $this->requiredDataFieldKeys);

        if (!empty($fields)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::CUSTOMER,
                $data['id'],
                implode(',', $fields)
            ));

            return new ConvertStruct(null, $oldData);
        }
        
        /* ... */
    }
    
    /* ... */
}
```
You can get the `LoggingService` from the service container. Use the `addLogEntry` method with a compatible instance of `LogEntryInterface`
and save the logging afterwards with `saveLogging`:
```php
<?php declare(strict_types=1);

interface LoggingServiceInterface
{
    public function addLogEntry(LogEntryInterface $logEntry): void;
    
    public function saveLogging(Context $context): void;
}
```

You should take a look at the already existing classes, which implement the `LogEntryInterface` to find one that fits your needs, just like the `EmptyNecessaryFieldRunLog` in the `CustomerConverter` example above.
All the general LogEntry classes are located under the following namespace `SwagMigrationAssistant\Migration\Logging\Log`.

To create a custom LogEntry make sure you at least implement the `LogEntryInterface` or, if your log happens during a running migration, you can also extend your LogEntry by the `BaseRunLogEntry`.

```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Migration\Logging\Log;

class EmptyNecessaryFieldRunLog extends BaseRunLogEntry
{
    /**
     * @var string
     */
    private $emptyField;

    public function __construct(string $runId, string $entity, string $sourceId, string $emptyField)
    {
        parent::__construct($runId, $entity, $sourceId);
        $this->emptyField = $emptyField;
    }

    public function getCode(): string
    {
        return sprintf('SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_%s', mb_strtoupper($this->getEntity()));
    }

    public function getLevel(): string
    {
        return self::LOG_LEVEL_WARNING;
    }

    public function getTitle(): string
    {
        return sprintf('The %s entity has one or more empty necessary fields', $this->getEntity());
    }

    public function getParameters(): array
    {
        return [
            'entity' => $this->getEntity(),
            'sourceId' => $this->getSourceId(),
            'emptyField' => $this->emptyField,
        ];
    }

    public function getDescription(): string
    {
        $args = $this->getParameters();

        return sprintf(
            'The %s entity with the source id %s does not have the necessary data for the field(s): %s',
                $args['entity'],
                $args['sourceId'],
                $args['emptyField']
            );
    }

    public function getTitleSnippet(): string
    {
        return sprintf('%s.%s.title', $this->getSnippetRoot(), 'SWAG_MIGRATION__SHOPWARE_EMPTY_NECESSARY_DATA_FIELDS');
    }

    public function getDescriptionSnippet(): string
    {
        return sprintf('%s.%s.description', $this->getSnippetRoot(), 'SWAG_MIGRATION__SHOPWARE_EMPTY_NECESSARY_DATA_FIELDS');
    }
}
```
The important part here is the `getCode` method. It should not contain any details, otherwise grouping won't work properly.
Also keep in mind to specify the English title and description in the respective `getTitle` and `getDescription` methods.
Create corresponding snippets with the same content for both the `getTitleSnippet` and `getDescriptionSnippet` method.

The English text is used in the international log file. Snippets instead are used all over the Administration, in order to inform or guide the user.
Parameters for the description should be returned by the `getParameters` method so the English description and snippets can both use them.
