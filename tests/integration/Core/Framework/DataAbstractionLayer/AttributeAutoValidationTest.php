<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;

/**
 * @internal
 */
class AttributeAutoValidationTest extends TestCase
{
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();

        $dummyTable = '
DROP TABLE IF EXISTS `dummy_entity`;
CREATE TABLE `dummy_entity` (
  `id` binary(16) NOT NULL,
  `ip` VARCHAR(255) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY `id` (`id`)
);
';

        $connection = self::getContainer()->get(Connection::class);
        $connection->executeQuery($dummyTable);
    }

    public function testAutoValidationAttributeEntity(): void
    {
        $repository = $this->getContainer()->get('dummy_entity.repository');

        $this->expectException(ConstraintViolationException::class);

        $repository?->create([[
            'id' => Uuid::randomHex(),
            'ip' => 'not.valid.ip',
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], Context::createCLIContext());
    }
}
