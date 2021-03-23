<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1616076922AppPaymentMethod;

class Migration1616076922AppPaymentMethodTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFolderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->mediaFolderRepository = $this->getContainer()->get('media_folder.repository');
    }

    public function testNoAppPaymentMethodTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_payment_method`');

        $migration = new Migration1616076922AppPaymentMethod();
        $migration->update($this->connection);
        $exists = $this->connection->fetchFirstColumn('SELECT COUNT(*) FROM `app_payment_method`') !== false;

        static::assertTrue($exists);
    }

    public function testDefaultFolder(): void
    {
        $context = Context::createDefaultContext();

        $this->connection->delete(
            'media_default_folder',
            [
                'entity' => PaymentMethodDefinition::ENTITY_NAME,
            ]
        );

        $migration = new Migration1616076922AppPaymentMethod();
        $migration->update($this->connection);

        $criteria = new Criteria();
        $criteria->addAssociation('defaultFolder');
        $criteria->addFilter(new EqualsFilter('defaultFolder.entity', PaymentMethodDefinition::ENTITY_NAME));
        $entities = $this->mediaFolderRepository->search($criteria, $context)->getEntities();
        static::assertCount(1, $entities);

        /** @var MediaFolderEntity|null $first */
        $first = $entities->first();
        static::assertNotNull($first);

        $defaultFolder = $first->getDefaultFolder();
        static::assertNotNull($defaultFolder);
        static::assertSame(['paymentMethods'], $defaultFolder->getAssociationFields());
    }
}
