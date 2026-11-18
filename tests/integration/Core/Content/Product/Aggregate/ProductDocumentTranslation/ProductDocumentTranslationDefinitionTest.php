<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocumentTranslation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentTranslationDefinitionTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    /**
     * @var EntityRepository<ProductDocumentCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
        $this->repository = static::getContainer()->get('product_document.repository');

        static::getContainer()->get('product.repository')->create([(new ProductBuilder($this->ids, 'drill'))->price(10)->build()], $this->context);
        static::getContainer()->get('media.repository')->create([
            ['id' => $this->ids->create('file'), 'fileName' => 'operating-manual', 'fileExtension' => 'pdf', 'fileSize' => 1024],
            ['id' => $this->ids->create('other-file'), 'fileName' => 'quick-start', 'fileExtension' => 'pdf', 'fileSize' => 1024],
        ], $this->context);
    }

    public function testATranslationInAnotherLanguageKeepsAnEmptyTitle(): void
    {
        $germanId = $this->getDeDeLanguageId();

        $this->repository->create([[
            'id' => $this->ids->create('document'),
            'productId' => $this->ids->get('drill'),
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual', 'mediaId' => $this->ids->get('file')],
                $germanId => ['title' => null, 'mediaId' => $this->ids->get('other-file')],
            ],
        ]], $this->context);

        $criteria = new Criteria([$this->ids->get('document')]);
        $criteria->addAssociation('translations');
        $titles = [];
        foreach ($this->repository->search($criteria, $this->context)->getEntities()->first()?->getTranslations() ?? [] as $translation) {
            $titles[$translation->getLanguageId()] = $translation->getTitle();
        }
        ksort($titles);

        $expected = [Defaults::LANGUAGE_SYSTEM => 'Manual', $germanId => null];
        ksort($expected);
        static::assertSame($expected, $titles);
    }

    public function testADocumentWithoutTitleIsRejected(): void
    {
        $exception = $this->catchWriteException(fn () => $this->repository->create([
            ['id' => $this->ids->create('document'), 'productId' => $this->ids->get('drill'), 'mediaId' => $this->ids->get('file')],
        ], $this->context));

        static::assertSame([
            ['code' => NotBlank::IS_BLANK_ERROR, 'pointer' => '/0/translations/' . Defaults::LANGUAGE_SYSTEM . '/title'],
        ], $this->errorsOf($exception));
    }

    public function testRemovingTheTitleIsRejected(): void
    {
        $this->repository->create([['id' => $this->ids->create('document'), 'productId' => $this->ids->get('drill'), 'title' => 'Manual', 'mediaId' => $this->ids->get('file')]], $this->context);

        $exception = $this->catchWriteException(fn () => $this->repository->update([
            ['id' => $this->ids->get('document'), 'title' => null],
        ], $this->context));

        static::assertSame([
            ['code' => NotBlank::IS_BLANK_ERROR, 'pointer' => '/0/translations/' . Defaults::LANGUAGE_SYSTEM . '/title'],
        ], $this->errorsOf($exception));
    }

    private function catchWriteException(\Closure $write): WriteException
    {
        try {
            $write();
        } catch (WriteException $exception) {
            return $exception;
        }

        static::fail('The write did not fail.');
    }

    /**
     * @return list<array{code: mixed, pointer: mixed}>
     */
    private function errorsOf(WriteException $exception): array
    {
        $errors = [];
        foreach ($exception->getErrors() as $error) {
            $errors[] = ['code' => $error['code'], 'pointer' => $error['source']['pointer'] ?? null];
        }

        return $errors;
    }
}
