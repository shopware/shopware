<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\Util\InMemoryUtilFilesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppExtractor::class)]
class AppExtractorTest extends TestCase
{
    public function testAppIsNotExtractedWhenValidationFails(): void
    {
        $validator = $this->createMock(AppArchiveValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(static::isInstanceOf(\ZipArchive::class), 'TestApp')
            ->willThrowException(AppArchiveValidationFailure::appEmpty());

        $extractor = new AppExtractor($validator, new InMemoryUtilFilesystem());

        $this->expectExceptionObject(AppArchiveValidationFailure::appEmpty());

        $extractor->extract(__DIR__ . '/_fixtures/App.zip', '/non-existing/destination', 'TestApp');
    }
}
