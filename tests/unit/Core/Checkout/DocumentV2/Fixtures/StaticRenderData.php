<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\Config\CompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
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

        $company = new CompanyInfo(
            'company',
            'street',
            '12345',
            'city',
            new CountryEntity()
        );

        parent::__construct(
            $config,
            $company,
            'date',
            'number',
            null,
        );
    }
}
