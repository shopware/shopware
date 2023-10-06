<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
class MediaExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSingleSearch(): void
    {
        $ids = new TestDataCollection();

        $data = [
            'id' => $ids->create('media'),
            'fileName' => 'testImage',
        ];

        $this->getContainer()->get('media.repository')
            ->create([$data], Context::createDefaultContext());

        $result = $this->render('search-media.html.twig', [
            'ids' => $ids->getList(['media']),
            'context' => Context::createDefaultContext(),
        ]);

        static::assertEquals('testImage/', $result);
    }

    public function testMultiSearch(): void
    {
        $ids = new TestDataCollection();

        $data = [
            ['id' => $ids->create('media-1'), 'fileName' => 'image-1'],
            ['id' => $ids->create('media-2'), 'fileName' => 'image-2'],
        ];

        $this->getContainer()->get('media.repository')
            ->create($data, Context::createDefaultContext());

        $result = $this->render('search-media.html.twig', [
            'ids' => $ids->getList(['media-1', 'media-2']),
            'context' => Context::createDefaultContext(),
        ]);

        static::assertEquals('image-1/image-2/', $result);
    }

    public function testEmptySearch(): void
    {
        $result = $this->render('search-media.html.twig', [
            'ids' => [],
            'context' => Context::createDefaultContext(),
        ]);

        static::assertEquals('', $result);
    }

    private function render(string $template, array $data): string
    {
        $twig = $this->getContainer()->get('twig');

        $originalLoader = $twig->getLoader();
        $twig->setLoader(new ArrayLoader([
            'test.html.twig' => file_get_contents(__DIR__ . '/fixture/' . $template),
        ]));
        $output = $twig->render('test.html.twig', $data);
        $twig->setLoader($originalLoader);

        return $output;
    }
}
