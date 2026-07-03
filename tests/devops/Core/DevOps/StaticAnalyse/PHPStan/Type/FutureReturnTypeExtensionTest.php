<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Type;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * @internal
 */
class FutureReturnTypeExtensionTest extends TypeInferenceTestCase
{
    #[RunInSeparateProcess]
    public function testAnnouncedReturnTypeWideningIsApplied(): void
    {
        foreach (static::gatherAssertTypes(__DIR__ . '/data/WidensReturn.php') as $args) {
            // because of the autoload issue we can not use data providers as phpstan does itself,
            // therefore we need to rely on this hacks
            $assertType = array_shift($args);
            static::assertIsString($assertType);
            $file = array_shift($args);
            static::assertIsString($file);

            $this->assertFileAsserts($assertType, $file, ...$args);
        }
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/data/future-return-type.neon',
        ];
    }
}
