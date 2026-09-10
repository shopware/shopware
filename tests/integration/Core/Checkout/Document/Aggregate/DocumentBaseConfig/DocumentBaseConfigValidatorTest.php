<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentBaseConfigValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const BOTH_PDF_FORMATS = ['/0/filenameInfixes/pdf', '/0/filenameInfixes/zugferd_embedded_pdf'];

    private Context $context;

    /**
     * @var EntityRepository<DocumentBaseConfigCollection>
     */
    private EntityRepository $documentBaseConfigRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->documentBaseConfigRepository = static::getContainer()->get('document_base_config.repository');
    }

    public function testRejectsSameInfixForBothPdfFormats(): void
    {
        try {
            $this->documentBaseConfigRepository->create([
                $this->salesChannelConfig('invoice', ['pdf' => '_same', 'zugferd_embedded_pdf' => '_same']),
            ], $this->context);
            static::fail('Expected the write to be rejected.');
        } catch (WriteException $exception) {
            static::assertSame(self::BOTH_PDF_FORMATS, $this->duplicateInfixPaths($exception));
        }
    }

    public function testMergesSeededGlobalInfixesIntoSalesChannelConfig(): void
    {
        $this->assertGlobalInvoiceInfixIsSeeded();

        try {
            $this->documentBaseConfigRepository->create([
                $this->salesChannelConfig('invoice', ['pdf' => '_zugferd']),
            ], $this->context);
            static::fail('Expected the write to be rejected.');
        } catch (WriteException $exception) {
            static::assertSame(self::BOTH_PDF_FORMATS, $this->duplicateInfixPaths($exception));
        }
    }

    public function testResolvesTypeAndScopeFromDatabaseWhenOnlyInfixesAreUpdated(): void
    {
        $config = $this->salesChannelConfig('invoice', ['pdf' => '_pdf']);
        $this->documentBaseConfigRepository->create([$config], $this->context);

        try {
            $this->documentBaseConfigRepository->update([[
                'id' => $config['id'],
                'filenameInfixes' => ['pdf' => '_same', 'zugferd_embedded_pdf' => '_same'],
            ]], $this->context);
            static::fail('Expected the write to be rejected.');
        } catch (WriteException $exception) {
            static::assertSame(self::BOTH_PDF_FORMATS, $this->duplicateInfixPaths($exception));
        }
    }

    public function testRevalidatesStoredInfixesWhenTheDocumentTypeChanges(): void
    {
        $this->assertGlobalInvoiceInfixIsSeeded();

        $config = $this->salesChannelConfig('delivery_note', ['pdf' => '_zugferd']);
        $this->documentBaseConfigRepository->create([$config], $this->context);

        try {
            $this->documentBaseConfigRepository->update([[
                'id' => $config['id'],
                'documentTypeId' => $this->documentTypeId('invoice'),
            ]], $this->context);
            static::fail('Expected the write to be rejected.');
        } catch (WriteException $exception) {
            static::assertSame(self::BOTH_PDF_FORMATS, $this->duplicateInfixPaths($exception));
        }
    }

    public function testRejectsGlobalInfixThatCollidesWithAnAssignedSalesChannelOverride(): void
    {
        $this->documentBaseConfigRepository->create([
            $this->salesChannelConfig('invoice', ['pdf' => '_shared'], name: 'Storefront invoice'),
        ], $this->context);

        try {
            $this->documentBaseConfigRepository->update([[
                'id' => $this->globalInvoiceConfigId(),
                'filenameInfixes' => ['zugferd_embedded_pdf' => '_shared'],
            ]], $this->context);
            static::fail('Expected the write to be rejected.');
        } catch (WriteException $exception) {
            static::assertSame(['/0/filenameInfixes/zugferd_embedded_pdf'], $this->duplicateInfixPaths($exception));
            static::assertSame('Storefront invoice', $this->firstViolation($exception)->getParameters()['{{ configs }}']);
        }
    }

    public function testIgnoresSalesChannelConfigsWithoutAnAssignment(): void
    {
        $unassigned = $this->salesChannelConfig('invoice', ['pdf' => '_shared']);
        unset($unassigned['salesChannels']);
        $this->documentBaseConfigRepository->create([$unassigned], $this->context);

        $this->documentBaseConfigRepository->update([[
            'id' => $this->globalInvoiceConfigId(),
            'filenameInfixes' => ['zugferd_embedded_pdf' => '_shared'],
        ]], $this->context);

        $written = $this->documentBaseConfigRepository->search(new Criteria([$this->globalInvoiceConfigId()]), $this->context)->getEntities()->first();
        static::assertInstanceOf(DocumentBaseConfigEntity::class, $written);
        static::assertSame(['zugferd_embedded_pdf' => '_shared'], $written->getFilenameInfixes());
    }

    public function testAcceptsDistinctInfixes(): void
    {
        $config = $this->salesChannelConfig('invoice', ['pdf' => '_pdf', 'zugferd_embedded_pdf' => '_e-invoice']);

        $this->documentBaseConfigRepository->create([$config], $this->context);

        $written = $this->documentBaseConfigRepository->search(new Criteria([$config['id']]), $this->context)->getEntities()->first();
        static::assertInstanceOf(DocumentBaseConfigEntity::class, $written);
        static::assertSame(['pdf' => '_pdf', 'zugferd_embedded_pdf' => '_e-invoice'], $written->getFilenameInfixes());
    }

    /**
     * @param array<string, string> $filenameInfixes
     *
     * @return array<string, mixed>
     */
    private function salesChannelConfig(string $documentType, array $filenameInfixes, string $name = 'config under test'): array
    {
        $documentTypeId = $this->documentTypeId($documentType);

        return [
            'id' => Uuid::randomHex(),
            'name' => $name,
            'documentTypeId' => $documentTypeId,
            'global' => false,
            'filenameInfixes' => $filenameInfixes,
            'salesChannels' => [[
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'documentTypeId' => $documentTypeId,
            ]],
        ];
    }

    private function documentTypeId(string $technicalName): string
    {
        $id = static::getContainer()->get('document_type.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName)), $this->context)
            ->firstId();
        static::assertIsString($id);

        return $id;
    }

    private function globalInvoiceConfigId(): string
    {
        $id = $this->documentBaseConfigRepository
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('typeName', 'invoice'), new EqualsFilter('global', true)), $this->context)
            ->firstId();
        static::assertIsString($id);

        return $id;
    }

    private function firstViolation(WriteException $exception): ConstraintViolationInterface
    {
        $inner = $exception->getExceptions()[0] ?? null;
        static::assertInstanceOf(WriteConstraintViolationException::class, $inner);

        return $inner->getViolations()->get(0);
    }

    private function assertGlobalInvoiceInfixIsSeeded(): void
    {
        $globalInfixes = $this->documentBaseConfigRepository
            ->search((new Criteria())->addFilter(new EqualsFilter('typeName', 'invoice'), new EqualsFilter('global', true)), $this->context)
            ->getEntities()
            ->first()
            ?->getFilenameInfixes();

        static::assertSame(['zugferd_embedded_pdf' => '_zugferd'], $globalInfixes, 'The migration seed this test relies on is missing.');
    }

    /**
     * @return list<string>
     */
    private function duplicateInfixPaths(WriteException $exception): array
    {
        $paths = [];
        foreach ($exception->getExceptions() as $inner) {
            static::assertInstanceOf(WriteConstraintViolationException::class, $inner);
            foreach ($inner->getViolations() as $violation) {
                static::assertSame(DocumentBaseConfigValidator::DUPLICATE_FILENAME_INFIX, $violation->getCode());
                $paths[] = $violation->getPropertyPath();
            }
        }

        return $paths;
    }
}
