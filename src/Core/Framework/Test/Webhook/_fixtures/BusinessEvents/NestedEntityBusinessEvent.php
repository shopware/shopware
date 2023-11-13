<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class NestedEntityBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    public function __construct(private readonly TaxEntity $tax)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('object', (new ObjectType())
                ->add('tax', new EntityType(TaxDefinition::class)));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getEncodeValues(string $shopwareVersion): array
    {
        return [
            'object' => [
                'tax' => [
                    'id' => $this->tax->getId(),
                    '_uniqueIdentifier' => $this->tax->getId(),
                    'versionId' => null,
                    'name' => $this->tax->getName(),
                    'taxRate' => $this->tax->getTaxRate(),
                    'position' => $this->tax->getPosition(),
                    'customFields' => null,
                    'translated' => [],
                    'createdAt' => $this->tax->getCreatedAt() ? $this->tax->getCreatedAt()->format(\DATE_RFC3339_EXTENDED) : null,
                    'updatedAt' => null,
                    'extensions' => [],
                    'apiAlias' => 'tax',
                ],
            ],
        ];
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public function getObject(): EntityBusinessEvent
    {
        return new EntityBusinessEvent($this->tax);
    }
}
