<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class GridPatternWriteTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testStructuredLayoutPersistsWithBreakpointOverrides(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);
        $configuration = [
            'xxl' => ['columns' => [['type' => 'auto']], 'areas' => [['a1']]],
            'lg' => [
                'columns' => [['type' => 'px', 'value' => 300], ['type' => 'percent', 'value' => 20], ['type' => 'fraction', 'value' => 4]],
                'areas' => [['a1', 'a2', 'a3'], ['a1', 'a4', 'a4']],
            ],
        ];
        $repository->create([$this->layout($ids, $configuration)], $context);
        $layout = $repository->search(new Criteria([$ids->get('layout')]), $context)->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);
        $storedConfiguration = $layout->getLayout()[0]->property('gridLayout')?->jsonSerialize();
        static::assertEquals($configuration, $storedConfiguration);
        static::assertSame(300, $storedConfiguration['lg']['columns'][0]['value']);
        static::assertSame(20, $storedConfiguration['lg']['columns'][1]['value']);
        static::assertSame(4, $storedConfiguration['lg']['columns'][2]['value']);
    }

    public function testContainerDoesNotRequireAStoredGridLayout(): void
    {
        $ids = new IdsCollection();
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);
        $layout = $this->layout($ids, null);
        unset($layout['layout'][0]['properties']['gridLayout']);

        $repository->create([$layout], Context::createDefaultContext());
        $stored = $repository->search(new Criteria([$ids->get('layout')]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $stored);
        static::assertNull($stored->getLayout()[0]->property('gridLayout'));
        static::assertSame('explicit', $stored->getLayout()[0]->property('mode')?->jsonSerialize());
    }

    /**
     * @return array<string, mixed>
     */
    private function layout(IdsCollection $ids, mixed $configuration): array
    {
        return [
            'id' => $ids->get('layout'),
            'name' => 'grid-layout',
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [[
                'id' => $ids->get('element'),
                'component' => 'Sw:Grid:Container',
                'properties' => ['gridLayout' => $configuration],
            ]],
        ];
    }
}
