<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Webhook\BusinessEventEncoder
 */
class BusinessEventEncoderTest extends TestCase
{
    public function testEncodeData(): void
    {
        $tax = $this->createMock(TaxEntity::class);
        $tax->expects(static::once())->method('getInternalEntityName')->willReturn('tax');

        $data = [
            'tax' => $tax,
            'array' => ['test'],
            'string' => 'test',
            'mail' => new MailRecipientStruct(['firstName' => 'name']),
        ];

        $stored = [
            'mail' => [
                'recipients' => ['firstName' => 'name'],
            ],
            'array' => ['test'],
            'string' => 'test',
        ];

        $entityEncoder = $this->createMock(JsonEntityEncoder::class);
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $businessEventEncoder = new BusinessEventEncoder($entityEncoder, $definitionRegistry);

        $data = $businessEventEncoder->encodeData($data, $stored);

        static::assertIsArray($data['tax']);
        static::assertIsArray($data['mail']);
        static::assertIsArray($data['array']);
        static::assertIsString($data['string']);
    }
}
