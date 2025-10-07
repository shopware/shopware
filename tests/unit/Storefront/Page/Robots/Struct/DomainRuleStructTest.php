<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct;

/**
 * @internal
 */
#[CoversClass(DomainRuleStruct::class)]
class DomainRuleStructTest extends TestCase
{
    /**
     * @param list<array{type: string, path: string}> $expectedRules
     */
    #[DataProvider('getTestCases')]
    public function testParsesDomainRulesCorrectly(string $ruleString, string $basePath, array $expectedRules): void
    {
        $domainRuleStruct = new DomainRuleStruct($ruleString, $basePath);

        static::assertSame($basePath, $domainRuleStruct->getBasePath());
        static::assertSame($expectedRules, $domainRuleStruct->getRules());
    }

    public function testGetPathRulesExcludesEmptyPathsAndNonPathDirectives(): void
    {
        $ruleString = "User-agent: *\nDisallow:\nDisallow: /admin/\nAllow:\nAllow: /public/\nCrawl-delay: 5";
        $domainRuleStruct = new DomainRuleStruct($ruleString, '/en');

        $pathRules = array_values($domainRuleStruct->getPathRules());

        static::assertCount(2, $pathRules, 'Should only have non-empty path rules');
        static::assertSame('Disallow', $pathRules[0]['type']);
        static::assertSame('/en/admin/', $pathRules[0]['path']);
        static::assertSame('Allow', $pathRules[1]['type']);
        static::assertSame('/en/public/', $pathRules[1]['path']);
    }

    public function testGetGlobalRulesIncludesEmptyPathsAndNonPathDirectives(): void
    {
        $ruleString = "User-agent: *\nDisallow:\nAllow: /public/\nCrawl-delay: 5\nSitemap: https://example.com/sitemap.xml";
        $domainRuleStruct = new DomainRuleStruct($ruleString, '');

        $globalRules = array_values($domainRuleStruct->getGlobalRules());

        static::assertCount(4, $globalRules, 'Should include User-agent, Crawl-delay, Sitemap, and empty Disallow');
        static::assertSame('User-agent', $globalRules[0]['type']);
        static::assertSame('*', $globalRules[0]['path']);
        static::assertSame('Disallow', $globalRules[1]['type']);
        static::assertSame('', $globalRules[1]['path'], 'Empty Disallow should be in global rules');
        static::assertSame('Crawl-delay', $globalRules[2]['type']);
        static::assertSame('5', $globalRules[2]['path']);
        static::assertSame('Sitemap', $globalRules[3]['type']);
        static::assertSame('https://example.com/sitemap.xml', $globalRules[3]['path']);
    }

    public function testBuildsUserAgentBlocks(): void
    {
        $ruleString = "User-agent: Googlebot\nDisallow:\nUser-agent: Bingbot\nCrawl-delay: 10\nAllow: /\nDisallow: /account/";
        $domainRuleStruct = new DomainRuleStruct($ruleString, '');

        $blocks = $domainRuleStruct->getUserAgentBlocks();

        static::assertCount(2, $blocks, 'Should have 2 user-agent blocks');

        // Block 1: Googlebot with Disallow:
        static::assertSame('Googlebot', $blocks[0]['userAgent']);
        static::assertCount(1, $blocks[0]['rules']);
        static::assertSame('Disallow', $blocks[0]['rules'][0]['type']);
        static::assertSame('', $blocks[0]['rules'][0]['path']);

        // Block 2: Bingbot with Crawl-delay and path rules
        static::assertSame('Bingbot', $blocks[1]['userAgent']);
        static::assertCount(3, $blocks[1]['rules']);
        static::assertSame('Crawl-delay', $blocks[1]['rules'][0]['type']);
        static::assertSame('10', $blocks[1]['rules'][0]['path']);
        static::assertSame('Allow', $blocks[1]['rules'][1]['type']);
        static::assertSame('/', $blocks[1]['rules'][1]['path']);
        static::assertSame('Disallow', $blocks[1]['rules'][2]['type']);
        static::assertSame('/account/', $blocks[1]['rules'][2]['path']);
    }

    public function testBuildsBlockWithoutUserAgent(): void
    {
        $ruleString = "Disallow: /account/\nAllow: /public/";
        $domainRuleStruct = new DomainRuleStruct($ruleString, '');

        $blocks = $domainRuleStruct->getUserAgentBlocks();

        static::assertCount(1, $blocks, 'Should have 1 block for default rules');
        static::assertNull($blocks[0]['userAgent'], 'Block without User-agent should have null');
        static::assertCount(2, $blocks[0]['rules']);
        static::assertSame('Disallow', $blocks[0]['rules'][0]['type']);
        static::assertSame('Allow', $blocks[0]['rules'][1]['type']);
    }

    public function testComplexBlockStructure(): void
    {
        $ruleString = "Disallow: /default/\nUser-agent: Googlebot\nDisallow:\nUser-agent: Bingbot\nCrawl-delay: 10\nAllow: /";
        $domainRuleStruct = new DomainRuleStruct($ruleString, '/en');

        $blocks = $domainRuleStruct->getUserAgentBlocks();

        static::assertCount(3, $blocks);

        // Block 1: Default rules (no user-agent)
        static::assertNull($blocks[0]['userAgent']);
        static::assertCount(1, $blocks[0]['rules']);
        static::assertSame('Disallow', $blocks[0]['rules'][0]['type']);
        static::assertSame('/en/default/', $blocks[0]['rules'][0]['path']);

        // Block 2: Googlebot
        static::assertSame('Googlebot', $blocks[1]['userAgent']);
        static::assertCount(1, $blocks[1]['rules']);
        static::assertSame('Disallow', $blocks[1]['rules'][0]['type']);
        static::assertSame('', $blocks[1]['rules'][0]['path']);

        // Block 3: Bingbot
        static::assertSame('Bingbot', $blocks[2]['userAgent']);
        static::assertCount(2, $blocks[2]['rules']);
        static::assertSame('Crawl-delay', $blocks[2]['rules'][0]['type']);
        static::assertSame('Allow', $blocks[2]['rules'][1]['type']);
        static::assertSame('/en/', $blocks[2]['rules'][1]['path']);
    }

    public function testUserAgentBlocksWithMultipleConsecutiveUserAgents(): void
    {
        // Note: In robots.txt spec, consecutive User-agents without rules between them
        // means those user-agents share the rules. However, our implementation
        // treats each User-agent as starting a new block.
        $ruleString = "User-agent: Googlebot\nUser-agent: Bingbot\nDisallow: /admin/\nUser-agent: Yahoo\nAllow: /";
        $domainRuleStruct = new DomainRuleStruct($ruleString, '');

        $blocks = $domainRuleStruct->getUserAgentBlocks();

        static::assertCount(2, $blocks, 'Should have 2 blocks (empty blocks are not saved)');

        // First saved block: Bingbot (Googlebot block was empty so not saved)
        static::assertSame('Bingbot', $blocks[0]['userAgent']);
        static::assertCount(1, $blocks[0]['rules']);
        static::assertSame('Disallow', $blocks[0]['rules'][0]['type']);
        static::assertSame('/admin/', $blocks[0]['rules'][0]['path']);

        // Second block: Yahoo
        static::assertSame('Yahoo', $blocks[1]['userAgent']);
        static::assertCount(1, $blocks[1]['rules']);
        static::assertSame('Allow', $blocks[1]['rules'][0]['type']);
        static::assertSame('/', $blocks[1]['rules'][0]['path']);
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
            'user-agent directive' => [
                'User-agent: Googlebot',
                '',
                [
                    ['type' => 'User-agent', 'path' => 'Googlebot'],
                ],
            ],
            'user-agent with disallow rules' => [
                "User-agent: Googlebot\nDisallow: /private/",
                '/en',
                [
                    ['type' => 'User-agent', 'path' => 'Googlebot'],
                    ['type' => 'Disallow', 'path' => '/en/private/'],
                ],
            ],
            'crawl-delay directive' => [
                'Crawl-delay: 10',
                '',
                [
                    ['type' => 'Crawl-delay', 'path' => '10'],
                ],
            ],
            'multiple user-agents with rules' => [
                "User-agent: Googlebot\nDisallow: /private/\nUser-agent: Bingbot\nAllow: /",
                '',
                [
                    ['type' => 'User-agent', 'path' => 'Googlebot'],
                    ['type' => 'Disallow', 'path' => '/private/'],
                    ['type' => 'User-agent', 'path' => 'Bingbot'],
                    ['type' => 'Allow', 'path' => '/'],
                ],
            ],
            'sitemap directive' => [
                'Sitemap: https://example.com/sitemap.xml',
                '',
                [
                    ['type' => 'Sitemap', 'path' => 'https://example.com/sitemap.xml'],
                ],
            ],
            'invalid rule types are ignored' => [
                "Invalid-Rule: /test/\nDisallow: /private/\nUnknown-Directive: value",
                '/en',
                [
                    ['type' => 'Disallow', 'path' => '/en/private/'],
                ],
            ],
            'empty path values are kept for allow/disallow' => [
                "Disallow:\nAllow:\nUser-agent:",
                '',
                [
                    ['type' => 'Disallow', 'path' => ''],
                    ['type' => 'Allow', 'path' => ''],
                ],
            ],
            'empty values ignored for non-path rules' => [
                "User-agent:\nCrawl-delay:\nDisallow: /admin/",
                '',
                [
                    ['type' => 'Disallow', 'path' => '/admin/'],
                ],
            ],
            'googlebot example with empty disallow' => [
                "User-agent: Googlebot\nDisallow:\nUser-agent: Googlebot-image\nDisallow:",
                '',
                [
                    ['type' => 'User-agent', 'path' => 'Googlebot'],
                    ['type' => 'Disallow', 'path' => ''],
                    ['type' => 'User-agent', 'path' => 'Googlebot-image'],
                    ['type' => 'Disallow', 'path' => ''],
                ],
            ],
            'empty disallow with base path should stay empty' => [
                "User-agent: *\nDisallow:",
                '/en',
                [
                    ['type' => 'User-agent', 'path' => '*'],
                    ['type' => 'Disallow', 'path' => ''],
                ],
            ],
            'whitespace handling' => [
                "  Allow:  /path/  \n  Disallow:  /admin/  ",
                '/de',
                [
                    ['type' => 'Allow', 'path' => '/de/path/'],
                    ['type' => 'Disallow', 'path' => '/de/admin/'],
                ],
            ],
            'mixed valid and invalid rules' => [
                "User-agent: *\nDisallow: /admin/\nInvalid: test\nAllow: /public/\nCrawl-delay: 5\nUnknown: value",
                '',
                [
                    ['type' => 'User-agent', 'path' => '*'],
                    ['type' => 'Disallow', 'path' => '/admin/'],
                    ['type' => 'Allow', 'path' => '/public/'],
                    ['type' => 'Crawl-delay', 'path' => '5'],
                ],
            ],
        ];
    }
}
