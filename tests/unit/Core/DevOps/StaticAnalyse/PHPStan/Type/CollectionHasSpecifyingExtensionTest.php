<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Type;

use PHPStan\Testing\TypeInferenceTestCase;

/**
 * @internal
 *
 * @covers \Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type\CollectionHasSpecifyingExtension
 */
class CollectionHasSpecifyingExtensionTest extends TypeInferenceTestCase
{
    /**
     * @runInSeparateProcess run in separate process to prevent autoloading issues, see https://github.com/phpstan/phpdoc-parser/issues/188
     */
    public function testCollectionHas(): void
    {
        foreach (static::gatherAssertTypes(__DIR__ . '/data/collection_has.php') as $args) {
            // because of the autoload issue we can not use data providers as phpstan does itself,
            // therefore we need to rely on this hacks
            $assertType = array_shift($args);
            $file = array_shift($args);

            $this->assertFileAsserts($assertType, $file, ...$args);
        }
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/data/extension.neon',
        ];
    }
}
