<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\SystemDefaultValidator;
use Shopware\Core\Content\ImportExport\Exception\DeleteDefaultProfileException;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class SystemDefaultValidatorTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<EntityCollection<ImportExportProfileEntity>>
     */
    private EntityRepository $repository;

    private Context $context;

    private SystemDefaultValidator $validator;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('import_export_profile.repository');
        $this->context = Context::createDefaultContext();
        $this->validator = new SystemDefaultValidator(static::getContainer()->get(Connection::class));
    }

    public function testSystemDefaultProfileDeletionIsRejected(): void
    {
        $id = $this->createProfile(true);
        $event = $this->createDeleteEvent($id);

        $this->validator->preValidate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        static::assertInstanceOf(DeleteDefaultProfileException::class, $event->getExceptions()->getExceptions()[0]);
    }

    public function testRegularProfileDeletionIsAllowed(): void
    {
        $id = $this->createProfile(false);
        $event = $this->createDeleteEvent($id);

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    private function createProfile(bool $systemDefault): string
    {
        $id = Uuid::randomHex();
        $profile = [
            'id' => $id,
            'technicalName' => 'test-' . $id,
            'systemDefault' => $systemDefault,
            'sourceEntity' => 'product',
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => [],
        ];

        if (!Feature::isActive('v6.8.0.0')) {
            $profile['label'] = 'Test profile';
        }

        $this->repository->create([$profile], $this->context);

        return $id;
    }

    private function createDeleteEvent(string $id): PreWriteValidationEvent
    {
        $command = new DeleteCommand(
            $this->repository->getDefinition(),
            ['id' => Uuid::fromHexToBytes($id)],
            EntityExistence::createEmpty(),
        );

        return new PreWriteValidationEvent(WriteContext::createFromContext($this->context), [$command]);
    }
}
