<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Steps;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Steps\FinishResult;
use Shopware\Core\Framework\Update\Steps\UnpackStep;

class UnpackStepTest extends TestCase
{
    public function testUnpackingOverCount(): void
    {
        $tempFile = sys_get_temp_dir() . '/' . uniqid(__FUNCTION__, true) . '.zip';
        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE);
        $zip->addFromString('test.txt', 'Test');
        $zip->close();

        $unpackStep = new UnpackStep($tempFile, sys_get_temp_dir());
        static::assertInstanceOf(FinishResult::class, $unpackStep->run(\PHP_INT_MAX));
    }
}
