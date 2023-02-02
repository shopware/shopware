<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;

/**
 * @internal
 */
class ScalarBusinessEvent implements BusinessEventInterface, BusinessEventEncoderTestInterface
{
    /**
     * @var string
     */
    private $string = 'string';

    /**
     * @var bool
     */
    private $bool = true;

    /**
     * @var int
     */
    private $int = 3;

    /**
     * @var float
     */
    private $float = 1.3;

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('string', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('bool', new ScalarValueType(ScalarValueType::TYPE_BOOL))
            ->add('int', new ScalarValueType(ScalarValueType::TYPE_INT))
            ->add('float', new ScalarValueType(ScalarValueType::TYPE_FLOAT));
    }

    public function getEncodeValues(string $shopwareVersion): array
    {
        return [
            'string' => 'string',
            'bool' => true,
            'int' => 3,
            'float' => 1.3,
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

    public function getString(): string
    {
        return $this->string;
    }

    public function isBool(): bool
    {
        return $this->bool;
    }

    public function getInt(): int
    {
        return $this->int;
    }

    public function getFloat(): float
    {
        return $this->float;
    }
}
