<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Preset;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\ContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Every preset the registry serves must be storable as it is inserted: its compiled payload is decoded the way the
 * insert-preset action decodes it and then written as a `content_layout`, which runs the stored-tree field
 * constraints, the property-type conformance pass among them.
 *
 * The write skips the resolvability gate, because a preset names no root source and so cannot be judged against
 * one; whether the inserted elements resolve is the layout's concern once a preset lands in it.
 *
 * @internal
 */
#[Package('framework')]
class ShippedPresetsConformanceTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('writes the compiled payload of every registered preset without a constraint violation')]
    public function testEveryRegisteredPresetPayloadPassesTheStoredTreeWritePath(): void
    {
        $registry = static::getContainer()->get(ContentSystemLayoutPresetRegistry::class);
        static::assertInstanceOf(AbstractContentSystemLayoutPresetRegistry::class, $registry);

        $presets = $registry->all();
        static::assertArrayHasKey('Sw:TabPanel', $presets, 'The shipped presets must be registered.');

        $decoder = static::getContainer()->get(DraftLayoutDecoder::class);
        $layoutRepository = static::getContainer()->get('content_layout.repository');

        $context = Context::createDefaultContext();
        $context->addState(LayoutGate::SKIP_VALIDATION_STATE);

        $rejected = [];

        foreach ($presets as $presetId => $preset) {
            $decoder->decode($preset->payload);

            try {
                $layoutRepository->create([[
                    'id' => Uuid::randomHex(),
                    'name' => 'preset-conformance-' . $presetId,
                    'version' => '1.0.0',
                    'rootSource' => 'none',
                    'layout' => $preset->payload,
                ]], $context);
            } catch (WriteException $exception) {
                $rejected[$presetId] = $exception->getMessage();
            }
        }

        static::assertSame([], $rejected, 'Every registered preset must write without a constraint violation.');
    }
}
