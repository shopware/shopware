<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Tcpdf\Fpdi;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DocumentMerger::class)]
class DocumentMergerTest extends TestCase
{
    public function testMergeWithFpdiConfig(): void
    {
        $fpdi = $this->createMock(Fpdi::class);
        $fpdi->expects(static::once())->method('setPrintHeader')
            ->willReturnCallback(
                function ($val): void {
                    static::assertFalse($val);
                }
            );
        $fpdi->expects(static::once())->method('setPrintFooter')
            ->willReturnCallback(
                function ($val): void {
                    static::assertFalse($val);
                }
            );

        $documentMerger = new DocumentMerger(
            $this->createMock(EntityRepository::class),
            $this->createMock(MediaService::class),
            $this->createMock(DocumentGenerator::class),
            $fpdi
        );

        $documentMerger->merge([Uuid::randomHex()], Context::createDefaultContext());
    }
}
