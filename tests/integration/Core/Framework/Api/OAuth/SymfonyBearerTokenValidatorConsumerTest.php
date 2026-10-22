<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\OAuth;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AdministrationSnippetService;
use Shopware\Core\Framework\Api\Controller\HealthCheckController;
use Shopware\Core\Framework\Api\EventListener\Authentication\ApiAuthenticationListener;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * Every service that validates bearer tokens on its own must reject tokens bound to the MCP resource (RFC 8707),
 * which SymfonyBearerTokenValidator does unless the caller opts in.
 * A new consumer has to be reviewed for that and added here.
 *
 * @internal
 */
#[Package('framework')]
class SymfonyBearerTokenValidatorConsumerTest extends TestCase
{
    use KernelTestBehaviour;

    private const REVIEWED_CONSUMERS = [
        // opts in for `/api/_mcp` only
        ApiAuthenticationListener::class,
        HealthCheckController::class,
        AdministrationSnippetService::class,
    ];

    /**
     * Containers of the test environment that reference every private service.
     */
    private const TEST_LOCATORS = [
        'test.private_services_locator',
    ];

    public function testOnlyReviewedServicesUseTheBearerTokenValidator(): void
    {
        $dump = static::getContainer()->getParameter('debug.container.dump');
        static::assertIsString($dump, 'The kernel of the integration tests runs in debug mode and dumps its container.');
        static::assertNotSame('', $dump);
        static::assertFileExists($dump);

        $document = new \DOMDocument();
        static::assertTrue($document->load($dump));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('c', 'http://symfony.com/schema/dic/services');

        $ids = [SymfonyBearerTokenValidator::class];
        foreach ($xpath->query(\sprintf('//c:services/c:service[@alias="%s"]', SymfonyBearerTokenValidator::class)) ?: [] as $alias) {
            \assert($alias instanceof \DOMElement);
            $ids[] = $alias->getAttribute('id');
        }

        $consumers = [];
        foreach ($ids as $id) {
            $usages = $xpath->query(\sprintf(
                '//c:argument[@type="service"][@id="%1$s"] | //c:property[@type="service"][@id="%1$s"] | //c:service[not(@id)][@class="%2$s"]',
                $id,
                SymfonyBearerTokenValidator::class
            )) ?: [];

            foreach ($usages as $usage) {
                static::assertInstanceOf(\DOMElement::class, $usage);
                $consumer = $this->findTopLevelService($usage);
                if ($consumer !== null && !\in_array($consumer, self::TEST_LOCATORS, true) && !str_starts_with($consumer, '.service_locator.')) {
                    $consumers[] = $consumer;
                }
            }
        }

        $consumers = array_values(array_unique($consumers));
        sort($consumers);
        $expected = self::REVIEWED_CONSUMERS;
        sort($expected);

        static::assertSame(
            $expected,
            $consumers,
            'A service starts or stops using SymfonyBearerTokenValidator. Check that it keeps rejecting tokens bound to the MCP resource and update the list.'
        );
    }

    private function findTopLevelService(\DOMNode $node): ?string
    {
        $topLevel = null;
        for ($current = $node->parentNode; $current instanceof \DOMElement; $current = $current->parentNode) {
            if ($current->localName === 'service' && $current->hasAttribute('id')) {
                $topLevel = $current->getAttribute('id');
            }
        }

        return $topLevel;
    }
}
