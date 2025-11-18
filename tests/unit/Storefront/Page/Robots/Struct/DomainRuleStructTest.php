<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;

/**
 * @internal
 */
#[CoversClass(DomainRuleStruct::class)]
class DomainRuleStructTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0 - Tests deprecated string format, will be removed
     *
     * @param list<array{type: string, path: string}> $expectedRules
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    #[DataProvider('getTestCases')]
    public function testParsesDomainRulesCorrectly(string $ruleString, string $basePath, array $expectedRules): void
    {
        $domainRuleStruct = new DomainRuleStruct($ruleString, $basePath);

        static::assertSame($basePath, $domainRuleStruct->getBasePath());

        $directives = $domainRuleStruct->getDirectives();
        static::assertCount(\count($expectedRules), $directives);

        foreach ($expectedRules as $index => $expectedRule) {
            static::assertSame($expectedRule['type'], $directives[$index]->type->value);
            static::assertSame($expectedRule['path'], $directives[$index]->value);
        }
    }

    /**
     * @return array<array{string, string, list<array{type: string, path: string}>}>
     */
    public static function getTestCases(): array
    {
        return [
            'empty string' => [
                '',
                '/en',
                [],
            ],
            'single disallow rule' => [
                'Disallow: /private/',
                '',
                [
                    ['type' => 'Disallow', 'path' => '/private/'],
                ],
            ],
            'single disallow with slash base path' => [
                'Disallow: /private/',
                '/',
                [
                    ['type' => 'Disallow', 'path' => '/private/'],
                ],
            ],
            'single disallow rule with base path' => [
                'Disallow: /private/',
                '/en',
                [
                    ['type' => 'Disallow', 'path' => '/en/private/'],
                ],
            ],
            'single allow rule' => [
                'Allow: /widgets/cms/',
                '',
                [
                    ['type' => 'Allow', 'path' => '/widgets/cms/'],
                ],
            ],
            'single allow rule with base path' => [
                'Allow: /widgets/cms/',
                '/en',
                [
                    ['type' => 'Allow', 'path' => '/en/widgets/cms/'],
                ],
            ],
            'multiple disallow rules with base path' => [
                "Disallow: /private/\nDisallow: /admin/",
                '/en',
                [
                    ['type' => 'Disallow', 'path' => '/en/private/'],
                    ['type' => 'Disallow', 'path' => '/en/admin/'],
                ],
            ],
            'multiple allow rules with base path' => [
                "Allow: /widgets/cms/\nAllow: /widgets/menu/",
                '/en',
                [
                    ['type' => 'Allow', 'path' => '/en/widgets/cms/'],
                    ['type' => 'Allow', 'path' => '/en/widgets/menu/'],
                ],
            ],
            'multiple rules' => [
                "Disallow: /private/\nDisallow: /admin/\nAllow: /widgets/cms/\nAllow: /widgets/menu/",
                '/',
                [
                    ['type' => 'Disallow', 'path' => '/private/'],
                    ['type' => 'Disallow', 'path' => '/admin/'],
                    ['type' => 'Allow', 'path' => '/widgets/cms/'],
                    ['type' => 'Allow', 'path' => '/widgets/menu/'],
                ],
            ],
            'multiple rules with base path' => [
                "Disallow: /private/\nDisallow: /admin/\nAllow: /widgets/cms/\nAllow: /widgets/menu/",
                '/en',
                [
                    ['type' => 'Disallow', 'path' => '/en/private/'],
                    ['type' => 'Disallow', 'path' => '/en/admin/'],
                    ['type' => 'Allow', 'path' => '/en/widgets/cms/'],
                    ['type' => 'Allow', 'path' => '/en/widgets/menu/'],
                ],
            ],
            'ignores user-agent and other directives in legacy mode' => [
                "User-agent: Googlebot\nCrawl-delay: 10\nDisallow: /admin/\nAllow: /public/",
                '',
                [
                    ['type' => 'Disallow', 'path' => '/admin/'],
                    ['type' => 'Allow', 'path' => '/public/'],
                ],
            ],
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - Tests deprecated string format, will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetDirectivesReturnsRobotsDirectiveObjects(): void
    {
        $domainRuleStruct = new DomainRuleStruct("Disallow: /private/\nAllow: /public/", '');

        $directives = $domainRuleStruct->getDirectives();

        static::assertCount(2, $directives);
        static::assertContainsOnlyInstancesOf(RobotsDirective::class, $directives);
    }
}
