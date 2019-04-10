<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class JsonSalesChannelApiEncoder extends JsonApiEncoder
{
    /**
     * @var string[][]
     */
    private $allowedRelationships = [];

    protected function serializeRelationships(Record $record, Entity $entity, JsonApiEncodingResult $result): void
    {
        $endpointName = $this->camelCaseToSnailCase($record->getType());

        $relationships = array_filter(
            $record->getRelationships(),
            function ($key) use ($endpointName) {
                return isset($this->allowedRelationships[$endpointName][$key]);
            },
            ARRAY_FILTER_USE_KEY
        );

        $record->setRelationships($relationships);

        parent::serializeRelationships($record, $entity, $result);
    }
}
