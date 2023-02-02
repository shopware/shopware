<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Commands;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Commands\GenerateMediaTypesCommand;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\TestCaseBase\CommandTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
class GenerateMediaTypesCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CommandTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepository
     */
    private $mediaRepository;

    /**
     * @var GenerateMediaTypesCommand
     */
    private $generateMediaTypesCommand;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    private Context $context;

    /**
     * @var array<string>
     */
    private array $initialMediaIds;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $this->generateMediaTypesCommand = $this->getContainer()->get(GenerateMediaTypesCommand::class);

        $this->context = Context::createDefaultContext();

        /** @var array<string> $ids */
        $ids = $this->mediaRepository->searchIds(new Criteria(), $this->context)->getIds();
        $this->initialMediaIds = $ids;
    }

    public function testExecuteHappyPath(): void
    {
        $this->createValidMediaFiles();

        $input = new StringInput('');
        $output = new BufferedOutput();

        $this->runCommand($this->generateMediaTypesCommand, $input, $output);

        $mediaResult = $this->getNewMediaEntities();
        /** @var MediaEntity $updatedMedia */
        foreach ($mediaResult as $updatedMedia) {
            static::assertInstanceOf(MediaType::class, $updatedMedia->getMediaType());
        }
    }

    public function testExecuteWithCustomBatchSize(): void
    {
        $this->createValidMediaFiles();

        $input = new StringInput('-b 1');
        $output = new BufferedOutput();

        $this->runCommand($this->generateMediaTypesCommand, $input, $output);

        $searchCriteria = new Criteria();
        $mediaResult = $this->mediaRepository->search($searchCriteria, $this->context);
        /** @var MediaEntity $updatedMedia */
        foreach ($mediaResult->getEntities() as $updatedMedia) {
            static::assertInstanceOf(MediaType::class, $updatedMedia->getMediaType());
        }
    }

    public function testExecuteWithMediaWithoutFile(): void
    {
        $this->setFixtureContext($this->context);
        $this->getEmptyMedia();

        $input = new StringInput('');
        $output = new BufferedOutput();

        $this->runCommand($this->generateMediaTypesCommand, $input, $output);

        $mediaResult = $this->getNewMediaEntities();
        /** @var MediaEntity $updatedMedia */
        foreach ($mediaResult as $updatedMedia) {
            static::assertNull($updatedMedia->getMediaType());
        }
    }

    public function testExecuteThrowsExceptionOnInvalidBatchSize(): void
    {
        $this->expectException(\Exception::class);
        $this->createValidMediaFiles();

        $input = new StringInput('-b "test"');
        $output = new BufferedOutput();

        $this->runCommand($this->generateMediaTypesCommand, $input, $output);
    }

    protected function createValidMediaFiles(): void
    {
        $this->setFixtureContext($this->context);
        $mediaPng = $this->getPng();
        $mediaJpg = $this->getJpg();
        $mediaPdf = $this->getPdf();

        $this->mediaRepository->upsert([
            [
                'id' => $mediaPng->getId(),
                'type' => null,
            ],
            [
                'id' => $mediaJpg->getId(),
                'type' => null,
            ],
            [
                'id' => $mediaPdf->getId(),
                'type' => null,
            ],
        ], $this->context);

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaPng);
        $this->getPublicFilesystem()->writeStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb')
        );

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaJpg);
        $this->getPublicFilesystem()->writeStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/shopware.jpg', 'rb')
        );

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaPdf);
        $this->getPublicFilesystem()->writeStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/small.pdf', 'rb')
        );
    }

    private function getNewMediaEntities(): MediaCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $this->initialMediaIds));
        $result = $this->mediaRepository->searchIds($criteria, $this->context);
        static::assertEquals(\count($this->initialMediaIds), $result->getTotal());

        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [
                new EqualsAnyFilter('id', $this->initialMediaIds),
            ]
        ));

        $entities = $this->mediaRepository->search($criteria, $this->context)->getEntities();
        static::assertInstanceOf(MediaCollection::class, $entities);

        return $entities;
    }
}
