<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Check;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class StoredResult
{
    public function __construct(
        public Result $result,
        public \DateTimeImmutable $checkedAt,
    ) {
    }

    /**
     * A row of the system_check_result table. Null for a status this version does not know.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): ?self
    {
        $status = Status::class . '::' . $row['status'];
        if (!\defined($status)) {
            return null;
        }

        return new self(
            new Result(
                name: (string) $row['name'],
                status: \constant($status),
                message: (string) $row['message'],
                healthy: $row['healthy'] === null ? null : (bool) $row['healthy'],
                extra: (array) json_decode((string) $row['extra'], true, flags: \JSON_THROW_ON_ERROR),
            ),
            new \DateTimeImmutable((string) $row['checked_at'], new \DateTimeZone('UTC')),
        );
    }
}
