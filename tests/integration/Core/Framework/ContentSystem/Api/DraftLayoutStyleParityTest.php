<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The container-wiring half of the style parity invariant, in two pins. The definition pin loads
 * `content-system.php` and asserts `DraftLayoutDecoder` and `LayoutWriteBoundary` reference one
 * `StoredTreeStyleNormalizer` service id (their two `service()` references sit far apart in that file),
 * which covers every style option without sampling. The behavioral pin resolves both services from DI and
 * compares their normalized output for one exercised option, so the wired instances demonstrably run. The
 * normalizer-behavior half — one hand-built instance feeding both paths — is the unit test of the same name.
 *
 * @internal
 */
#[Package('framework')]
class DraftLayoutStyleParityTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('wires DraftLayoutDecoder and LayoutWriteBoundary to one StoredTreeStyleNormalizer service id')]
    public function testBothServicesReferenceOneNormalizerServiceId(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator()))
            ->load(\dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection/content-system.php');

        static::assertSame([StoredTreeStyleNormalizer::class], $this->normalizerReferences($container, LayoutWriteBoundary::class));
        static::assertSame([StoredTreeStyleNormalizer::class], $this->normalizerReferences($container, DraftLayoutDecoder::class));
    }

    #[TestDox('yields the same style shape from the container-wired draft decode path as the container-wired write boundary')]
    public function testContainerWiredDraftDecodeMatchesWriteBoundaryStyle(): void
    {
        // A partially specified breakpoint map of a core option that declares a default ("auto"): the
        // normalizer fills the missing breakpoints, so a path wired without it produces a visibly smaller map.
        $raw = [[
            'id' => 'parity-element',
            'component' => TestElementTypeLoader::DEFAULTED_PRIMITIVE,
            'properties' => ['headline' => 'Parity'],
            'style' => ['align-self' => ['xs' => 'center']],
        ]];

        $draftStyle = static::getContainer()->get(DraftLayoutDecoder::class)
            ->decode($raw)[0]->style->toArray();

        $written = static::getContainer()->get(LayoutWriteBoundary::class)
            ->apply(static::getContainer()->get(StoredTreeCodec::class)->decode($raw));
        $writtenStyle = $written->roots[0]->style->toArray();

        static::assertSame($writtenStyle, $draftStyle);

        // Guards the equivalence against being vacuously true on an untouched map. It depends on the
        // `align-self` declaration (Definitions/align-self.yaml) staying breakpoint-aware with a default:
        // a red HERE after a change to that YAML asks for a new expanding fixture option, not a wiring fix.
        static::assertIsArray($draftStyle['align-self']);
        static::assertSame(Breakpoint::values(), array_keys($draftStyle['align-self']));
    }

    /**
     * Every constructor argument of the service that references the normalizer, by id. Matching by id
     * rather than by argument index keeps the pin alive across a constructor reorder; a service handed a
     * second normalizer under a NEW id answers `[]` here and fails the comparison.
     *
     * @param class-string $serviceId
     *
     * @return list<string>
     */
    private function normalizerReferences(ContainerBuilder $container, string $serviceId): array
    {
        $references = [];

        foreach ($container->getDefinition($serviceId)->getArguments() as $argument) {
            if ($argument instanceof Reference && (string) $argument === StoredTreeStyleNormalizer::class) {
                $references[] = (string) $argument;
            }
        }

        return $references;
    }
}
