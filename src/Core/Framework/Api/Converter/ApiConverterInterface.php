<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

interface ApiConverterInterface
{
    /**
     * Returns the EntityName of the entity that should be processed
     */
    public function getProcessedEntityName(): string;

    /**
     * Returns the Api-Version the deprecation was introduced
     */
    public function getDeprecatedApiVersion(): int;

    /**
     * Called to convert an Request from an old Api version to a the current version
     */
    public function convertEntityPayloadToCurrentVersion(array $payload): array;

    /**
     * Called to convert a response in a new ApiVersion to an OldVersion
     */
    public function isFieldFromFuture(string $fieldName): bool;

    /**
     * Called to strip all deprecated Fields from the response or check the request
     */
    public function isFieldDeprecated(string $fieldName): bool;
}
