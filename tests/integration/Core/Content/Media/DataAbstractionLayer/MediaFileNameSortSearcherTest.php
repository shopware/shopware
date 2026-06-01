<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1780315490AddMediaFileNameSortKey;

/**
 * @internal
 */
#[Package('discovery')]
class MediaFileNameSortSearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    private Context $context;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        (new Migration1780315490AddMediaFileNameSortKey())->update($connection);

        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testMediaFileNameSortingUsesInternalGeneratedColumn(): void
    {
        $alphaId = Uuid::randomHex();
        $zuluId = Uuid::randomHex();

        $this->mediaRepository->create([
            [
                'id' => $zuluId,
                'private' => false,
                'fileName' => 'zulu',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
            ],
            [
                'id' => $alphaId,
                'private' => false,
                'fileName' => 'alpha',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
            ],
        ], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', [$alphaId, $zuluId]));
        $criteria->addSorting(new FieldSorting('fileName'));

        static::assertSame(
            [$alphaId, $zuluId],
            $this->mediaRepository->searchIds($criteria, $this->context)->getIds()
        );
    }
}
