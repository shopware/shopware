<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class StaticRenderData extends AbstractRenderData
{
    public function __construct(
        public string $testData = 'test',
    ) {
        $config = new DocumentConfig(
            'a4',
            'portrait',
            10
        );

        $company = new DocumentCompanyInfo(
            'company',
            'street',
            '12345',
            'city',
            new CountryEntity()
        );

        parent::__construct(
            $config,
            $company,
            new DocumentDisplayOptions(),
            'date',
            'number',
            null,
            [],
        );
    }
}
