<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck\Check;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\StoredResult;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredResult::class)]
class StoredResultTest extends TestCase
{
    public function testFromRow(): void
    {
        $stored = StoredResult::fromRow([
            'name' => 'AppUrlVerification',
            'status' => 'WARNING',
            'healthy' => 0,
            'message' => 'APP_URL could not be verified',
            'extra' => '{"statusCode":503}',
            'checked_at' => '2026-11-24 10:00:00.000',
        ]);

        static::assertEquals(
            new StoredResult(
                new Result('AppUrlVerification', Status::WARNING, 'APP_URL could not be verified', false, ['statusCode' => 503]),
                new \DateTimeImmutable('2026-11-24 10:00:00', new \DateTimeZone('UTC')),
            ),
            $stored,
        );
    }

    public function testUnknownStatusIsLeftOut(): void
    {
        static::assertNull(StoredResult::fromRow([
            'name' => 'AppUrlVerification',
            'status' => 'MAYBE',
            'healthy' => null,
            'message' => '',
            'extra' => '[]',
            'checked_at' => '2026-11-24 10:00:00.000',
        ]));
    }
}
