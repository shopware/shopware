<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @internal
 */
class UnstructuredObjectBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    private array $nested = [
        'string' => 'test',
        'bool' => true,
    ];

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('nested', new ObjectType());
    }

    public function getEncodeValues(string $shopwareVersion): array
    {
        return [
            'nested' => [
                'string' => 'test',
                'bool' => true,
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

    public function getNested(): array
    {
        return $this->nested;
    }
}
