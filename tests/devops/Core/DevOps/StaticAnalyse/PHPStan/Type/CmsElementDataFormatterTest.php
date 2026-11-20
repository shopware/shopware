<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Type;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\Output;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Testing\PHPStanTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type\CmsElementDataFormatter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class CmsElementDataFormatterTest extends PHPStanTestCase
{
    public function testFailedAndEmptyAnalysesDoNotPublishTypes(): void
    {
        $filesystem = new Filesystem();
        $directory = $filesystem->tempnam(sys_get_temp_dir(), 'cms-formatter-');
        $filesystem->remove($directory);
        $filesystem->mkdir($directory);
        try {
            $manifest = $directory . '/manifest.json';
            $output = $directory . '/context.json';
            $filesystem->dumpFile($manifest, '{"resolvers":[],"methods":{}}');
            $formatter = new CmsElementDataFormatter($manifest, $output, self::getContainer()->getByType(TypeStringResolver::class));
            // @phpstan-ignore phpstanApi.constructor (Fixture for the pinned PHPStan version.)
            $failed = new AnalysisResult([], ['Analysis failed'], [], [], [], true, null, false, 0, false);
            static::assertSame(2, $formatter->formatErrors($failed, static::createStub(Output::class)));
            static::assertFileDoesNotExist($output);
            // @phpstan-ignore phpstanApi.constructor (Fixture for the pinned PHPStan version.)
            $empty = new AnalysisResult([], [], [], [], [], true, null, false, 0, false);
            static::assertSame(2, $formatter->formatErrors($empty, static::createStub(Output::class)));
            static::assertFileDoesNotExist($output);
        } finally {
            $filesystem->remove($directory);
        }
    }
}
