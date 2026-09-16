<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\EventDataType;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BusinessEventEncoder::class)]
class BusinessEventEncoderTest extends TestCase
{
    public function testEncodeData(): void
    {
        $tax = new TaxEntity();
        // Needed that the `_entityName` property is set correctly
        $tax->getApiAlias();

        $data = [
            'tax' => $tax,
            'array' => ['test'],
            'string' => 'test',
            'mail' => new MailRecipientStruct(['firstName' => 'name']),
            'notExistentInStored' => 'notExistentInStored',
        ];

        $stored = [
            'mail' => [
                'recipients' => ['firstName' => 'name'],
            ],
            'array' => ['test'],
            'string' => 'test',
        ];

        $entityEncoder = static::createStub(JsonEntityEncoder::class);
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $businessEventEncoder = new BusinessEventEncoder($entityEncoder, $definitionRegistry);

        $data = $businessEventEncoder->encodeData($data, $stored);

        static::assertIsArray($data['tax']);
        static::assertIsArray($data['mail']);
        static::assertIsArray($data['array']);
        static::assertIsString($data['string']);

        static::assertArrayHasKey('notExistentInStored', $data);
        static::assertSame('notExistentInStored', $data['notExistentInStored']);
    }

    public function testDeclaredEntityIsEncoded(): void
    {
        $event = $this->createEvent(new EntityType(TaxDefinition::class), new TaxEntity());

        static::assertSame(['payload' => ['encoded' => true]], $this->createEncoder()->encode($event));
    }

    public function testPlainObjectTypePassesArraysThrough(): void
    {
        $event = $this->createEvent(new ObjectType(), ['string' => 'a', 'bool' => true]);

        static::assertSame(['payload' => ['string' => 'a', 'bool' => true]], $this->createEncoder()->encode($event));
    }

    public function testHiddenFromWebhookFieldIsOmittedFromPayload(): void
    {
        $event = $this->createEvent(
            new ScalarValueType(ScalarValueType::TYPE_STRING),
            'sw-context-token-value',
            [EventDataCollection::HIDDEN_FROM_WEBHOOK => true]
        );

        static::assertSame([], $this->createEncoder()->encode($event));
    }

    private function createEncoder(): BusinessEventEncoder
    {
        $entityEncoder = static::createStub(JsonEntityEncoder::class);
        $entityEncoder->method('encode')->willReturn(['encoded' => true]);

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('get')->willReturn(new TaxDefinition());

        return new BusinessEventEncoder($entityEncoder, $definitionRegistry);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createEvent(EventDataType $declared, mixed $value, array $options = []): FlowEventAware
    {
        return new class($declared, $value, $options) implements FlowEventAware {
            private static EventDataType $declared;

            /**
             * @var array<string, mixed>
             */
            private static array $options;

            /**
             * @param array<string, mixed> $options
             */
            public function __construct(EventDataType $declared, private readonly mixed $value, array $options)
            {
                self::$declared = $declared;
                self::$options = $options;
            }

            public static function getAvailableData(): EventDataCollection
            {
                return (new EventDataCollection())->add('payload', self::$declared, self::$options);
            }

            public function getName(): string
            {
                return 'test.event';
            }

            public function getContext(): Context
            {
                return Context::createDefaultContext();
            }

            public function getPayload(): mixed
            {
                return $this->value;
            }
        };
    }
}
