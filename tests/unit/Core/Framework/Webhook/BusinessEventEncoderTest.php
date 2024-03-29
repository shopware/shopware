<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
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
