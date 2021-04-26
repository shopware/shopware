<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxDefinition;

class CollectionBusinessEvent implements BusinessEventInterface, BusinessEventEncoderTestInterface
{
    /**
     * @var TaxCollection
     */
    private $taxes;

    public function __construct(TaxCollection $taxes)
    {
        $this->taxes = $taxes;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('taxes', new EntityCollectionType(TaxDefinition::class));
    }

    public function getEncodeValues(string $shopwareVersion): array
    {
        $taxes = [];

        foreach ($this->taxes->getElements() as $tax) {
            $taxes[] = [
                'id' => $tax->getId(),
                '_uniqueIdentifier' => $tax->getId(),
                'versionId' => null,
                'name' => $tax->getName(),
                'taxRate' => (int) $tax->getTaxRate(),
                'position' => $tax->getPosition(),
                'customFields' => null,
                'translated' => [],
                'createdAt' => $tax->getCreatedAt()->format(\DATE_RFC3339_EXTENDED),
                'updatedAt' => null,
                'extensions' => [
                    'foreignKeys' => [
                        'extensions' => [],
                        'apiAlias' => null,
                    ],
                ],
                'apiAlias' => 'tax',
            ];
        }

        return [
            'taxes' => $taxes,
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

    public function getTaxes(): TaxCollection
    {
        return $this->taxes;
    }
}
