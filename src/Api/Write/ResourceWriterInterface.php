<?php declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: oliver
 * Date: 27.10.17
 * Time: 10:24
 */

namespace Shopware\Api\Write;

use Shopware\Api\Write\FieldAware\FieldExtenderCollection;

interface ResourceWriterInterface
{
    public function upsert(
        string $resourceClass,
        array $rawData,
        WriteContext $writeContext,
        FieldExtenderCollection $extender
    ): array;

    public function insert(
        string $resourceClass,
        array $rawData,
        WriteContext $writeContext,
        FieldExtenderCollection $extender
    );

    public function update(
        string $resourceClass,
        array $rawData,
        WriteContext $writeContext,
        FieldExtenderCollection $extender
    );
}
