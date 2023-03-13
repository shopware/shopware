<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class ArrayBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    /**
     * @var TaxEntity[]
     */
    private readonly array $taxes;

    public function __construct(TaxCollection $taxes)
    {
        $this->taxes = $taxes->getElements();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('taxes', new ArrayType(new EntityType(TaxDefinition::class)));
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function getEncodeValues(string $shopwareVersion): array
    {
        $taxes = [];

        foreach ($this->taxes as $tax) {
            $taxes[] = [
                'id' => $tax->getId(),
                '_uniqueIdentifier' => $tax->getId(),
                'versionId' => null,
                'name' => $tax->getName(),
                'taxRate' => $tax->getTaxRate(),
                'position' => $tax->getPosition(),
                'customFields' => null,
                'translated' => [],
                'createdAt' => $tax->getCreatedAt() ? $tax->getCreatedAt()->format(\DATE_RFC3339_EXTENDED) : null,
                'updatedAt' => null,
                'extensions' => [],
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

    /**
     * @return TaxEntity[]
     */
    public function getTaxes(): array
    {
        return $this->taxes;
    }
}
