<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Element\Style;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemStyleOption\AppContentSystemStyleOptionCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\ContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AppStyleOptionPoisonRowTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function setUp(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $appId = $ids->get('app');
        $appName = 'AcmeStyleOptionPoison' . $ids->get('appNameSuffix');

        $this->appRepository()->create([[
            'id' => $appId,
            'name' => $appName,
            'path' => 'AcmeStyleOptionPoison',
            'version' => '1.0.0',
            'label' => 'Acme Style Option Poison',
            'active' => true,
            'integration' => ['label' => $appName, 'accessKey' => 'style-option-poison-' . $appId, 'secretAccessKey' => 'style-option-poison-' . $appId],
            'aclRole' => ['name' => $appName],
        ]], $context);

        $this->styleOptionRepository()->create([[
            'id' => $ids->get('style-option'),
            'appId' => $appId,
            'name' => 'poison-option',
            'schema' => ['type' => 'not-a-style-option-type'],
            'hash' => 'poison-hash',
        ]], $context);

        $this->registry()->invalidate();
    }

    protected function tearDown(): void
    {
        $this->registry()->invalidate();
    }

    #[TestDox('aborts registry construction when an active app has an invalid persisted style option')]
    public function testInvalidActiveAppRowAbortsRegistryConstruction(): void
    {
        try {
            $this->registry()->all();
            static::fail('Expected the invalid active-app style option row to abort registry construction.');
        } catch (ContentSystemException $exception) {
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
            static::assertSame(ContentSystemException::STYLE_OPTION_LOAD_FAILED, $exception->getErrorCode());
            static::assertStringContainsString('poison-option', $exception->getMessage());
        }
    }

    private function registry(): AbstractContentSystemStyleOptionRegistry
    {
        $registry = $this->getContainer()->get(ContentSystemStyleOptionRegistry::class);
        static::assertInstanceOf(AbstractContentSystemStyleOptionRegistry::class, $registry);

        return $registry;
    }

    /**
     * @return EntityRepository<AppCollection>
     */
    private function appRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('app.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<AppContentSystemStyleOptionCollection>
     */
    private function styleOptionRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('app_content_system_style_option.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
