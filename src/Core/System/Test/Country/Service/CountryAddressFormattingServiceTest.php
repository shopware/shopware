<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Country\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Country\Service\CountryAddressFormattingService;
use Shopware\Core\System\Country\Struct\CountryAddress;

/**
 * @internal
 */
class CountryAddressFormattingServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CountryAddressFormattingService $countryAddressFormattingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->countryAddressFormattingService = $this->getContainer()->get(CountryAddressFormattingService::class);
    }

    /**
     * @dataProvider dataProviderTestRender
     */
    public function testRender(array $address, ?string $template, string $expected): void
    {
        $actual = $this->countryAddressFormattingService->render(
            CountryAddress::createFromEntityJsonSerialize($address),
            $template,
            Context::createDefaultContext(),
        );

        static::assertEquals($expected, $actual);
    }

    public function dataProviderTestRender(): \Generator
    {
        yield 'render correctly' => [
            [
                'firstName' => 'Duy',
                'lastName' => 'Dinh',
                'street' => 'abc',
                'city' => 'Vietnam',
                'zipcode' => '55000',
            ],
            "{{firstName}}\n{{lastName}}",
            "Duy\nDinh",
        ];

        yield 'prevent render if template is null' => [
            [
                'firstName' => 'Duy',
                'lastName' => 'Dinh',
                'street' => 'abc',
                'city' => 'Vietnam',
                'zipcode' => '55000',
            ],
            null,
            '',
        ];

        yield 'prevent empty line if the variable is null' => [
            [
                'firstName' => 'Duy',
                'lastName' => 'Dinh',
                'company' => null,
                'street' => 'abc',
                'city' => 'Vietnam',
                'zipcode' => '55000',
            ],
            "{{firstName}}\n{{company}}\n{{lastName}}",
            "Duy\nDinh",
        ];
    }
}
