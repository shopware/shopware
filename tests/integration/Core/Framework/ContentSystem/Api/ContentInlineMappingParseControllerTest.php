<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContentInlineMappingParseControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testParsesAnInlineMappingToken(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/inline-mapping/parse', [
            'text' => '{{map:category.description}}',
            'revision' => 7,
        ]);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame([
            'revision' => 7,
            'tokens' => [[
                'path' => 'category.description',
                'match' => '{{map:category.description}}',
                'canonicalToken' => '{{map:category.description}}',
                'offset' => 0,
                'insideMarkup' => false,
                'malformed' => false,
            ]],
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }
}
