<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\Framework;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('framework')]
class HttpMethodOverrideTest extends TestCase
{
    #[DataProvider('configurationProvider')]
    public function testPostOverridesAreLimitedToFormActions(string $file): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../../../src/Core/Framework/Resources/config/packages/' . $file);
        $config = $config['framework'];
        $originalAllowedMethods = Request::getAllowedHttpMethodOverride();
        $originalParameterOverride = Request::getHttpMethodParameterOverride();

        try {
            Request::setAllowedHttpMethodOverride($config['allowed_http_method_override'] ?? null);
            if ($config['http_method_override'] === true) {
                Request::enableHttpMethodParameterOverride();
            }

            foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT'] as $method) {
                $expected = \in_array($method, ['PUT', 'PATCH', 'DELETE'], true) ? $method : 'POST';

                $headerRequest = Request::create('/', 'POST');
                $headerRequest->headers->set('X-HTTP-Method-Override', $method);
                static::assertSame($expected, $headerRequest->getMethod(), 'Header override to ' . $method);

                $formRequest = Request::create('/', 'POST', ['_method' => $method]);
                static::assertSame($expected, $formRequest->getMethod(), 'Form override to ' . $method);
            }

            foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
                static::assertSame($method, Request::create('/', $method)->getMethod());
            }
        } finally {
            Request::setAllowedHttpMethodOverride($originalAllowedMethods);
            (new \ReflectionProperty(Request::class, 'httpMethodParameterOverride'))->setValue(null, $originalParameterOverride);
        }
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function configurationProvider(): \Generator
    {
        yield 'application forms and API clients' => ['framework.yaml'];
        yield 'installer forms' => ['installer.yaml'];
    }
}
