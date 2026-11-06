<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Workspace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Workspace\ContentWorkspaceWriteValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentWorkspaceWriteValidator::class)]
class ContentWorkspaceWriteValidatorTest extends TestCase
{
    public function testAcceptsEmptyWorkspace(): void
    {
        $validator = new ContentWorkspaceWriteValidator(Validation::createValidator());
        static::assertCount(0, $validator->validateConfiguration([
            'frames' => [], 'previewSalesChannelId' => null, 'canvas' => ['panX' => 0, 'panY' => 0, 'zoom' => 1],
        ]));
    }

    public function testAcceptsCustomFrameAndPreviewContext(): void
    {
        $validator = new ContentWorkspaceWriteValidator(Validation::createValidator());
        static::assertCount(0, $validator->validateConfiguration([
            'frames' => [self::frame()], 'previewSalesChannelId' => str_repeat('c', 32),
            'canvas' => ['panX' => -140.5, 'panY' => 80, 'zoom' => 0.5],
        ]));
    }

    #[DataProvider('invalidFrameProvider')]
    public function testRejectsInvalidFrames(string $field, mixed $value): void
    {
        $frame = self::frame();
        $frame[$field] = $value;
        $validator = new ContentWorkspaceWriteValidator(Validation::createValidator());
        static::assertNotCount(0, $validator->validateConfiguration([
            'frames' => [$frame], 'previewSalesChannelId' => null, 'canvas' => ['panX' => 0, 'panY' => 0, 'zoom' => 1],
        ]));
    }

    /**
     * @return \Generator<string, array{string, mixed}>
     */
    public static function invalidFrameProvider(): \Generator
    {
        yield 'layout IDs must be UUIDs' => ['layoutId', 'bad'];
        yield 'preview IDs cannot be empty' => ['previewEntityId', ''];
        yield 'frame widths have a minimum' => ['width', 319];
        yield 'frame heights have a maximum' => ['height', 1801];
        yield 'coordinates cannot be numeric strings' => ['x', '5001'];
        yield 'coordinates must be finite' => ['y', \INF];
        yield 'viewport values are known' => ['viewport', 'large'];
        yield 'layout content cannot be embedded' => ['layout', []];
    }

    public function testRejectsDuplicateLayoutsAndInvalidCamera(): void
    {
        $validator = new ContentWorkspaceWriteValidator(Validation::createValidator());
        $violations = $validator->validateConfiguration([
            'frames' => [self::frame(), self::frame()], 'previewSalesChannelId' => null,
            'canvas' => ['panX' => \NAN, 'panY' => 0, 'zoom' => 2],
        ]);
        static::assertCount(3, $violations);
    }

    /**
     * @return array<string, mixed>
     */
    private static function frame(): array
    {
        return [
            'layoutId' => str_repeat('a', 32), 'x' => -40, 'y' => 80, 'width' => 700, 'height' => 900,
            'viewport' => 'custom', 'previewEntityId' => str_repeat('b', 32),
        ];
    }
}
