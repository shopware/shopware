<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;

class NestedEntityBusinessEvent implements BusinessEventInterface, BusinessEventEncoderTestInterface
{
    /**
     * @var TaxEntity
     */
    private $tax;

    public function __construct(TaxEntity $tax)
    {
        $this->tax = $tax;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('object', (new ObjectType())
                ->add('tax', new EntityType(TaxDefinition::class)));
    }

    public function getEncodeValues(string $shopwareVersion): array
    {
        if (version_compare($shopwareVersion, '6.3.0.0', '<')) {
            $createdAt = $this->tax->getCreatedAt()->format(DATE_ATOM);
            $foreignKeys = [
                'extensions' => [],
            ];
        } else {
            $createdAt = $this->tax->getCreatedAt()->format(DATE_RFC3339_EXTENDED);
            $foreignKeys = [
                'extensions' => [],
                'apiAlias' => null,
            ];
        }

        return [
            'object' => [
                'tax' => [
                    'id' => $this->tax->getId(),
                    '_uniqueIdentifier' => $this->tax->getId(),
                    'versionId' => null,
                    'name' => $this->tax->getName(),
                    'taxRate' => (int) $this->tax->getTaxRate(),
                    'products' => null,
                    'customFields' => null,
                    'translated' => [],
                    'createdAt' => $createdAt,
                    'updatedAt' => null,
                    'extensions' => [
                        'foreignKeys' => $foreignKeys,
                    ],
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
