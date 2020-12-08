<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Language\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Locale\LocaleCollection;

class LanguageRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'languageId' => $this->ids->get('language'),
            'languages' => [
                ['id' => $this->ids->get('language')],
                ['id' => $this->ids->get('language2')],
            ],
            'domains' => [
                [
                    'languageId' => $this->ids->get('language'),
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost',
                ],
                [
                    'languageId' => $this->ids->get('language2'),
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost/second',
                ],
            ],
        ]);
    }

    public function testLanguages(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/language',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $ids = array_column($response, 'id');
        $names = array_column($response, 'name');

        static::assertCount(2, $response);
        static::assertContains($this->ids->get('language'), $ids);
        static::assertContains($this->ids->get('language2'), $ids);
        static::assertContains($this->ids->get('language2'), $ids);
        static::assertContains('match', $names);
        static::assertContains('match2', $names);
        static::assertEmpty($response[0]['locale']);
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/language',
                [
                    'includes' => [
                        'language' => ['name'],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertCount(2, $response);
        static::assertArrayHasKey('name', $response[0]);
        static::assertArrayNotHasKey('id', $response[0]);
    }

    public function testAssociation(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/language',
                [
                    'associations' => [
                        'locale' => [],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertCount(2, $response);
        static::assertArrayHasKey('locale', $response[0]);
        static::assertNotEmpty($response[0]['locale']);
        static::assertArrayHasKey('id', $response[0]['locale']);
    }

    private function createData(): void
    {
        /** @var LocaleCollection $locales */
        $locales = $this->getContainer()->get('locale.repository')->search(new Criteria(), $this->ids->context);

        $data = [
            [
                'id' => $this->ids->create('language'),
                'name' => 'match',
                'localeId' => $locales->first()->getId(),
                'translationCodeId' => $locales->first()->getId(),
            ],
            [
                'id' => $this->ids->create('language2'),
                'name' => 'match2',
                'localeId' => $locales->last()->getId(),
                'translationCodeId' => $locales->last()->getId(),
            ],
        ];

        $this->getContainer()->get('language.repository')
            ->create($data, $this->ids->context);
    }
}
