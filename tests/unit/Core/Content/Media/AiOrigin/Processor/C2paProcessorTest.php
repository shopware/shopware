<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Content\Media\AiOrigin\Processor\C2paProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(C2paProcessor::class)]
class C2paProcessorTest extends TestCase
{
    /**
     * @param array<string, array{actions?: list<array<string, mixed>>, templates?: list<array<string, mixed>>, ingredients?: list<array<string, mixed>>}> $manifests
     */
    #[DataProvider('manifestStores')]
    public function testProcess(array $manifests, ?MediaAiOrigin $expected): void
    {
        $processor = new C2paProcessor();

        static::assertSame($expected, $processor->process(new ImageProvenance(c2paManifestStores: [Image::c2paStore($manifests)])));
    }

    public static function manifestStores(): \Generator
    {
        yield 'created by a generative model (OpenAI, Firefly text to image)' => [
            ['urn:c2pa:1' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]]],
            MediaAiOrigin::Generated,
        ];

        yield 'created as a composite with AI (Google Pixel Magic Editor)' => [
            ['urn:c2pa:1' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::COMPOSITE]]]],
            MediaAiOrigin::Modified,
        ];

        yield 'photo edited with generative fill (Photoshop)' => [
            ['urn:c2pa:1' => ['actions' => [
                ['action' => 'c2pa.opened'],
                ['action' => 'c2pa.edited', 'digitalSourceType' => Image::TRAINED, 'softwareAgent' => 'Adobe Firefly'],
            ]]],
            MediaAiOrigin::Modified,
        ];

        yield 'image of a third-party model opened in an Adobe app is generated' => [
            ['urn:c2pa:1' => ['actions' => [
                ['action' => 'c2pa.opened', 'parameters' => ['com.adobe.digitalSourceType' => Image::TRAINED, 'com.adobe.type' => 'remoteProvider.3rdParty']],
            ]]],
            MediaAiOrigin::Generated,
        ];

        yield 'source type from an action template applies to the created action' => [
            ['urn:c2pa:1' => ['actions' => [['action' => 'c2pa.created']], 'templates' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]]],
            MediaAiOrigin::Generated,
        ];

        yield 'source type from the "*" template applies to an edit' => [
            ['urn:c2pa:1' => ['actions' => [['action' => 'c2pa.opened'], ['action' => 'c2pa.edited']], 'templates' => [['action' => '*', 'digitalSourceType' => Image::COMPOSITE]]]],
            MediaAiOrigin::Modified,
        ];

        yield 'own source type of an action wins over the template' => [
            ['urn:c2pa:1' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::DIGITAL_CAPTURE]], 'templates' => [['action' => '*', 'digitalSourceType' => Image::TRAINED]]]],
            null,
        ];

        yield 'camera capture without AI (Leica, Pixel camera)' => [
            ['urn:c2pa:1' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::DIGITAL_CAPTURE]]]],
            null,
        ];

        yield 'cropped copy of an AI-generated parent stays generated' => [
            [
                'urn:c2pa:parent' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]],
                'urn:c2pa:active' => [
                    'actions' => [['action' => 'c2pa.opened'], ['action' => 'c2pa.cropped']],
                    'ingredients' => [Image::ingredient('parentOf', 'urn:c2pa:parent')],
                ],
            ],
            MediaAiOrigin::Generated,
        ];

        yield 'AI-generated component placed into a photo makes it modified' => [
            [
                'urn:c2pa:sticker' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]],
                'urn:c2pa:photo' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::DIGITAL_CAPTURE]]],
                'urn:c2pa:active' => [
                    'actions' => [['action' => 'c2pa.opened'], ['action' => 'c2pa.placed']],
                    'ingredients' => [
                        Image::ingredient('parentOf', 'urn:c2pa:photo'),
                        Image::ingredient('componentOf', 'urn:c2pa:sticker'),
                    ],
                ],
            ],
            MediaAiOrigin::Modified,
        ];

        yield 'component with its own AI source type (C2PA 2.4 ingredient) makes it modified' => [
            ['urn:c2pa:active' => [
                'actions' => [['action' => 'c2pa.opened'], ['action' => 'c2pa.placed']],
                'ingredients' => [['relationship' => 'componentOf', 'digitalSourceType' => Image::TRAINED]],
            ]],
            MediaAiOrigin::Modified,
        ];

        yield 'update manifest of an AI-modified parent stays modified' => [
            [
                'urn:c2pa:parent' => ['actions' => [['action' => 'c2pa.opened'], ['action' => 'c2pa.edited', 'digitalSourceType' => Image::COMPOSITE]]],
                'urn:c2pa:update' => [
                    'actions' => [['action' => 'c2pa.opened']],
                    'ingredients' => [Image::ingredient('parentOf', 'urn:c2pa:parent')],
                ],
            ],
            MediaAiOrigin::Modified,
        ];

        yield 'reference image used as input does not count' => [
            [
                'urn:c2pa:reference' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]],
                'urn:c2pa:active' => [
                    'actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::DIGITAL_CAPTURE]],
                    'ingredients' => [Image::ingredient('inputTo', 'urn:c2pa:reference')],
                ],
            ],
            null,
        ];

        yield 'only the active manifest and its ingredients count, not unrelated manifests' => [
            [
                'urn:c2pa:old' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]],
                'urn:c2pa:active' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::DIGITAL_CAPTURE]]],
            ],
            null,
        ];

        yield 'self-referencing ingredient does not loop' => [
            ['urn:c2pa:active' => ['actions' => [['action' => 'c2pa.opened']], 'ingredients' => [Image::ingredient('parentOf', 'urn:c2pa:active')]]],
            null,
        ];
    }

    public function testLegacyAdobeGenerativeAiAssertionIsGenerated(): void
    {
        $legacy = Image::superbox('com.adobe.generative-ai', Image::box('cbor', Image::cbor(['description' => 'Adobe Firefly', 'version' => '1.0'])));
        $store = Image::superbox('c2pa', Image::superbox('urn:uuid:1', Image::superbox('c2pa.assertions', $legacy)));

        $processor = new C2paProcessor();

        static::assertSame(MediaAiOrigin::Generated, $processor->process(new ImageProvenance(c2paManifestStores: [$store])));
    }

    public function testReadsJsonAssertions(): void
    {
        $actions = Image::superbox('c2pa.actions', Image::box('json', (string) json_encode(['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]])));

        static::assertSame(MediaAiOrigin::Generated, $this->process(Image::superbox('c2pa', Image::superbox('urn:uuid:1', Image::superbox('c2pa.assertions', $actions)))));
    }

    public function testSkipsAssertionsNestedTooDeep(): void
    {
        $nested = [];
        for ($i = 0; $i < 40; ++$i) {
            $nested = ['a' => $nested];
        }
        $actions = Image::superbox('c2pa.actions.v2', Image::box('cbor', Image::cbor(['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED, 'parameters' => $nested]]])));

        static::assertNull($this->process(Image::superbox('c2pa', Image::superbox('urn:uuid:1', Image::superbox('c2pa.assertions', $actions)))));
    }

    public function testSkipsAssertionsWithInvalidCbor(): void
    {
        $actions = Image::superbox('c2pa.actions.v2', Image::box('cbor', "\x9f\x01"));

        static::assertNull($this->process(Image::superbox('c2pa', Image::superbox('urn:uuid:1', Image::superbox('c2pa.assertions', $actions)))));
    }

    public function testIgnoresStoresWithoutC2paLabel(): void
    {
        $actions = Image::superbox('c2pa.actions.v2', Image::box('cbor', Image::cbor(['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]])));

        static::assertNull($this->process(Image::superbox('other', Image::superbox('urn:uuid:1', Image::superbox('c2pa.assertions', $actions)))));
    }

    public function testStopsAtTruncatedBoxesWithoutError(): void
    {
        $store = Image::c2paStore(['urn:uuid:1' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]]]);

        static::assertNull($this->process(substr($store, 0, 40)));
    }

    public function testWithoutManifestStoreReturnsNull(): void
    {
        $processor = new C2paProcessor();

        static::assertNull($processor->process(new ImageProvenance(xmpPackets: [Image::xmp(Image::TRAINED)])));
        static::assertNull($processor->process(new ImageProvenance(c2paManifestStores: ['garbage'])));
    }

    private function process(string $store): ?MediaAiOrigin
    {
        return (new C2paProcessor())->process(new ImageProvenance(c2paManifestStores: [$store]));
    }
}
