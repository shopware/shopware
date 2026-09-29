<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class StaticDocumentDataProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'fixture';

    /**
     * @param list<string> $documentTypes
     * @param \ArrayObject<int, ProviderInput>|null $receivedInputs
     */
    public function __construct(
        private array $documentTypes = [DocumentType::INVOICE->value],
        private string $key = self::KEY,
        private ?\ArrayObject $receivedInputs = null,
    ) {
    }

    public function supports(string $documentType): bool
    {
        return \in_array($documentType, $this->documentTypes, true);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $criteria->addAssociation('lineItems');
    }

    public function provideRenderingData(
        ProviderInput $input,
        Context $context,
    ): AbstractRenderData {
        $this->receivedInputs?->append($input);

        return $this->key === DocumentMetaProvider::KEY ? $this->createDefaultMetaRenderData() : new StaticRenderData();
    }

    private function createDefaultMetaRenderData(): DocumentMetaRenderData
    {
        return new DocumentMetaRenderData(
            config: new DocumentConfig(
                pageSize: 'a4',
                pageOrientation: 'portrait',
                itemsPerPage: 10,
            ),
            company: new DocumentCompanyInfo(
                'Example',
                'Example Street 1',
                '12345',
                'Example City',
                new CountryEntity(),
            ),
            display: new DocumentDisplayOptions(),
            documentDate: '2024-01-01 00:00:00',
            documentNumber: '12345',
            documentComment: null,
        );
    }
}
