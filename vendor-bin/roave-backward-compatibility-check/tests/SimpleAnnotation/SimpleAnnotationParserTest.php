<?php declare(strict_types=1);

namespace Shopware\RoaveBackwardCompatibility\Tests\SimpleAnnotation;

use PHPUnit\Framework\TestCase;
use Shopware\RoaveBackwardCompatibility\SimpleAnnotation\ParseConfig;
use Shopware\RoaveBackwardCompatibility\SimpleAnnotation\SimpleAnnotationParser;

class SimpleAnnotationParserTest extends TestCase
{
    /**
     * @dataProvider cases
     */
    public function testParsing(string $annotation, array $expected): void
    {
        $config = new ParseConfig(['path', 'name']);
        static::assertEquals($expected, SimpleAnnotationParser::parse($annotation, $config));
    }

    public function cases(): iterable
    {
        yield [
            '("/account/order/{deepLinkCode}", name="frontend.account.order.single.page", options={"seo"="false"}, methods={"GET", "POST"})',
            [
                'path' => '/account/order/{deepLinkCode}',
                'name' => 'frontend.account.order.single.page',
                'options' => [
                    'seo' => 'false',
                ],
                'methods' => ['GET', 'POST'],
            ],
        ];

        yield [
            '("/%shopware_administration.path_name%", defaults={"auth_required"=false}, name="administration.index", methods={"GET"})',
            [
                'path' => '/%shopware_administration.path_name%',
                'name' => 'administration.index',
                'defaults' => [
                    'auth_required' => 'false',
                ],
                'methods' => ['GET'],
            ],
        ];

        yield [
            '("/detail/{productId}/switch", name="frontend.detail.switch", methods={"GET", "POST"}, defaults={"XmlHttpRequest": true})',
            [
                'path' => '/detail/{productId}/switch',
                'name' => 'frontend.detail.switch',
                'defaults' => [
                    'XmlHttpRequest' => 'true',
                ],
                'methods' => ['GET', 'POST'],
            ],
        ];

        yield [
            '"GET", "POST"',
            [
                'path' => 'GET',
                'name' => 'POST',
            ],
        ];

        yield [
            '"seo"="false"',
            [
                'seo' => 'false',
            ],
        ];

        yield [
            '("country/country-state-data", name="frontend.country.country.data", defaults={"csrf_protected"=false, "XmlHttpRequest"=true}, methods={ "POST" })',
            [
                'path' => 'country/country-state-data',
                'name' => 'frontend.country.country.data',
                'defaults' => [
                    'csrf_protected' => 'false',
                    'XmlHttpRequest' => 'true',
                ],
                'methods' => ['POST'],
            ],
        ];

        yield [
            '("/checkout/line-item/add", name="frontend.checkout.line-item.add", methods={"POST"}, defaults={"XmlHttpRequest"=true})',
            [
                'path' => '/checkout/line-item/add',
                'name' => 'frontend.checkout.line-item.add',
                'defaults' => [
                    'XmlHttpRequest' => 'true',
                ],
                'methods' => ['POST'],
            ],
        ];

        yield [
            '("/api/_action/clone/{entity}/{id}", name="api.clone", methods={"POST"}, requirements={
          "version"="\d+", "entity"="[a-zA-Z-]+", "id"="[0-9a-f]{32}"
      })',
            [
                'path' => '/api/_action/clone/{entity}/{id}',
                'name' => 'api.clone',
                'requirements' => [
                    'version' => '\d+',
                    'entity' => '[a-zA-Z-]+',
                    'id' => '[0-9a-f]{32}',
                ],
                'methods' => ['POST'],
            ],
        ];

        yield [
            '()',
            [],
        ];

        yield [
            '({"promotion.editor"})',
            [
                'path' => ['promotion.editor'],
            ],
        ];
    }
}
