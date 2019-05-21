[titleEn]: <>(Logging)

Logging is essential for anyone using the Shopware Migration Assistant. In case of failure it enables users to find out
why part of their data might be missing. Most of the logging takes place in the `Converter` classes, each time they detect
missing required values. Also every exception will create a log entry automatically. Here is an example of how the logging
of warnings works in the `CustomerConverter`:
```php
<?php declare(strict_types=1);

class CustomerConverter extends Shopware55Converter
{
    /* ... */
    
    public function convert(
            array $data,
            Context $context,
            MigrationContextInterface $migrationContext
        ): ConvertStruct {
        $oldData = $data;
        $this->runId = $migrationContext->getRunUuid();

        $fields = $this->checkForEmptyRequiredDataFields($data, $this->requiredDataFieldKeys);

        if (!empty($fields)) {
            // This will add an entry in the `swag_migration_logging` table, because there are some necessary fields missing
            $this->loggingService->addWarning(
                $this->runId,
                Shopware55LogTypes::EMPTY_NECESSARY_DATA_FIELDS,
                'Empty necessary data fields',
                sprintf('Customer-Entity could not be converted cause of empty necessary field(s): %s.', implode(', ', $fields)),
                [
                    'id' => $data['id'],
                    'entity' => 'Customer',
                    'fields' => $fields,
                ],
                \count($fields)
            );

            return new ConvertStruct(null, $oldData);
        }
        
        /* ... */
    }
    
    /* ... */
}
```
You can get the `LoggingService` from the service container. It has different methods depending on the log level:
```php
<?php declare(strict_types=1);

interface LoggingServiceInterface
{
    public function addInfo(string $runId, string $code, string $title, string $description, array $details = [], int $counting = 0): void;

    public function addWarning(string $runId, string $code, string $title, string $description, array $details = [], int $counting = 0): void;

    public function addError(string $runId, string $code, string $title, string $description, array $details = [], int $counting = 0): void;

    public function saveLogging(Context $context): void;
}
```
The `details` parameter holds extra information on the error.
The `counting` parameter is used to make the error snippet capable of handling single as well as multiple missing values.

Keep in mind to create a new snippet, when creating a custom log type or throwing a custom exception.
Be sure the snippet resembles this format: `swag-migration.index.error.SWAG_MIGRATION__SHOPWARE55_EMPTY_NECESSARY_DATA_FIELDS`.
The last part of the snippet is the log type or exception error code.
