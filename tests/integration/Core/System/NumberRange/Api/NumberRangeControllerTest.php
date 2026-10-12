<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\NumberRange\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class NumberRangeControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testPatternCollisionsListsOtherNumberRangesOfTheSameDocumentType(): void
    {
        $typeId = $this->getNumberRangeTypeId('document_invoice');
        $firstId = $this->createNumberRange($typeId, 'Invoices shop A', 'COLLIDE-{n}');
        $secondId = $this->createNumberRange($typeId, 'Invoices shop B', 'COLLIDE-{n}');
        $this->createNumberRange($typeId, 'Invoices shop C', 'DISTINCT-{n}');

        $collisions = $this->requestCollisions([
            'typeId' => $typeId,
            'pattern' => 'COLLIDE-{n}',
            'numberRangeId' => $firstId,
        ]);

        static::assertSame([['id' => $secondId, 'name' => 'Invoices shop B']], $collisions);
    }

    public function testPatternCollisionsWithoutNumberRangeIdIncludesEveryMatch(): void
    {
        $typeId = $this->getNumberRangeTypeId('document_invoice');
        $firstId = $this->createNumberRange($typeId, 'Invoices shop A', 'NEW-{n}');
        $secondId = $this->createNumberRange($typeId, 'Invoices shop B', 'NEW-{n}');

        $collisions = $this->requestCollisions([
            'typeId' => $typeId,
            'pattern' => 'NEW-{n}',
        ]);

        $ids = array_column($collisions, 'id');
        sort($ids);
        $expected = [$firstId, $secondId];
        sort($expected);

        static::assertSame($expected, $ids);
    }

    public function testPatternCollisionsIgnoresOtherDocumentTypes(): void
    {
        $invoiceTypeId = $this->getNumberRangeTypeId('document_invoice');
        $creditNoteTypeId = $this->getNumberRangeTypeId('document_credit_note');
        $invoiceId = $this->createNumberRange($invoiceTypeId, 'Invoices', 'SHARED-{n}');
        $this->createNumberRange($creditNoteTypeId, 'Credit notes', 'SHARED-{n}');

        $collisions = $this->requestCollisions([
            'typeId' => $invoiceTypeId,
            'pattern' => 'SHARED-{n}',
            'numberRangeId' => $invoiceId,
        ]);

        static::assertSame([], $collisions);
    }

    public function testPatternCollisionsIgnoresNonDocumentNumberRangeTypes(): void
    {
        $typeId = $this->getNumberRangeTypeId('customer');
        $firstId = $this->createNumberRange($typeId, 'Customers shop A', 'CUST-{n}');
        $this->createNumberRange($typeId, 'Customers shop B', 'CUST-{n}');

        $collisions = $this->requestCollisions([
            'typeId' => $typeId,
            'pattern' => 'CUST-{n}',
            'numberRangeId' => $firstId,
        ]);

        static::assertSame([], $collisions);
    }

    public function testPatternCollisionsRequiresTypeIdAndPattern(): void
    {
        $browser = $this->getBrowser();

        $browser->jsonRequest('GET', '/api/_action/number-range/pattern-collisions?pattern=' . rawurlencode('{n}'));
        static::assertSame(400, $browser->getResponse()->getStatusCode());

        $browser->jsonRequest('GET', '/api/_action/number-range/pattern-collisions?typeId=' . $this->getNumberRangeTypeId('document_invoice'));
        static::assertSame(400, $browser->getResponse()->getStatusCode());

        $browser->jsonRequest('GET', '/api/_action/number-range/pattern-collisions?typeId=not-a-uuid&pattern=' . rawurlencode('{n}'));
        static::assertSame(400, $browser->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, string> $query
     *
     * @return list<array{id: string, name: string}>
     */
    private function requestCollisions(array $query): array
    {
        $browser = $this->getBrowser();
        $browser->jsonRequest('GET', '/api/_action/number-range/pattern-collisions?' . http_build_query($query));

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertArrayHasKey('collisions', $content);

        return $content['collisions'];
    }

    private function createNumberRange(string $typeId, string $name, string $pattern): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('number_range.repository')->create([[
            'id' => $id,
            'typeId' => $typeId,
            'name' => $name,
            'pattern' => $pattern,
            'start' => 1,
            'global' => false,
        ]], Context::createDefaultContext());

        return $id;
    }

    private function getNumberRangeTypeId(string $technicalName): string
    {
        $typeId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `number_range_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName]
        );

        static::assertIsString($typeId);

        return $typeId;
    }
}
