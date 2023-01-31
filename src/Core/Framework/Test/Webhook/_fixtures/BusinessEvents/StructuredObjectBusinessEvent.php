<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @internal
 */
class StructuredObjectBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    private readonly ScalarBusinessEvent $inner;

    public function __construct()
    {
        $this->inner = new ScalarBusinessEvent();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(
                'inner',
                (new ObjectType())
                    ->add('string', new ScalarValueType(ScalarValueType::TYPE_STRING))
                    ->add('bool', new ScalarValueType(ScalarValueType::TYPE_BOOL))
                    ->add('int', new ScalarValueType(ScalarValueType::TYPE_INT))
                    ->add('float', new ScalarValueType(ScalarValueType::TYPE_FLOAT))
            );
    }

    public function getEncodeValues(string $shopwareVersion): array
    {
        return [
            'inner' => [
                'string' => 'string',
                'bool' => true,
                'int' => 3,
                'float' => 1.3,
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

    public function getInner(): ScalarBusinessEvent
    {
        return $this->inner;
    }
}
