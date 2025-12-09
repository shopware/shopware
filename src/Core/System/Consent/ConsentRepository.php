<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\DTO\ConsentDTO;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(string $name, string $storage): void
    {
        // todo: validate storage, we can't load services from the container here
        //        if (!isset($this->stores[$storage])) {
        //            throw ConsentException::invalidStorage($storage, array_keys($this->stores));
        //        }

        try {
            $this->connection->insert('consent', [
                'id' => Uuid::randomBytes(),
                'name' => $name,
                'storage' => $storage,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw ConsentException::alreadyExists($name);
        }
    }

    /**
     * @return array<ConsentDTO>
     */
    public function fetchAll(): array
    {
        $result = $this->connection->fetchAllAssociative('SELECT * FROM consent');

        return array_map(function (array $row) {
            $createdAt = \DateTimeImmutable::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $row['created_at']);
            $updatedAt = $row['updated_at'] ? \DateTimeImmutable::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $row['created_at']) : null;

            if ($createdAt === false || $updatedAt === false) {
                throw ConsentException::invalidConsent();
            }

            return new ConsentDTO(
                id: $row['id'],
                name: $row['name'],
                storage: $row['storage'],
                createdAt: $createdAt,
                updatedAt: $updatedAt,
            );
        }, $result);
    }
}
