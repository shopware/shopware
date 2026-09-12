<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\StorefrontSeoUrlError;
use Shopware\Core\Framework\App\Validation\StorefrontSeoUrlValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Validation\RouteBlocklistService;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontSeoUrlValidator::class)]
class StorefrontSeoUrlValidatorTest extends TestCase
{
    private StorefrontSeoUrlValidator $validator;

    /**
     * @var list<string>
     */
    private array $matchingPaths = [];

    protected function setUp(): void
    {
        $router = static::createStub(RouterInterface::class);
        $router->method('getContext')->willReturn(new RequestContext());
        $router->method('match')->willReturnCallback(function (string $path): array {
            if (!\in_array($path, $this->matchingPaths, true)) {
                throw new ResourceNotFoundException($path);
            }

            return ['_route' => 'some.existing.route'];
        });

        $this->validator = new StorefrontSeoUrlValidator(new RouteBlocklistService($router));
    }

    public function testManifestWithoutStorefrontIsValid(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/Xml/Gateways/_fixtures/testGateway/manifest.xml');

        static::assertCount(0, $this->validator->validate($manifest, Context::createDefaultContext()));
    }

    public function testBothVariantsOfTheSharedFixtureAreValid(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        static::assertCount(0, $this->validator->validate($manifest, Context::createDefaultContext()));
    }

    public function testDuplicateNamesAreRejected(): void
    {
        $errors = $this->validate(
            <<<'XML'
                <seo-url name="imprint">
                    <path>imprint</path>
                </seo-url>
                <seo-url name="imprint">
                    <path>legal</path>
                </seo-url>
                XML
        );

        static::assertStringContainsString('imprint: the name is used by more than one seo-url', $errors);
    }

    public function testEntityBoundSeoUrlWithAPathIsRejected(): void
    {
        $errors = $this->validate(
            <<<'XML'
                <seo-url name="blog-detail" entity="ce_blog">
                    <path>blog</path>
                    <default-template>blog/{{ ceBlog.id }}</default-template>
                </seo-url>
                XML
        );

        static::assertStringContainsString('blog-detail: an entity bound seo-url must not define a path', $errors);
    }

    public function testSeoUrlWithNeitherEntityNorPathIsRejected(): void
    {
        $errors = $this->validate('<seo-url name="imprint"/>');

        static::assertStringContainsString('imprint: a seo-url must define either an entity or at least one path', $errors);
    }

    public function testEntityBoundSeoUrlWithoutDefaultTemplateIsRejected(): void
    {
        $errors = $this->validate('<seo-url name="blog-detail" entity="ce_blog"/>');

        static::assertStringContainsString('blog-detail: an entity bound seo-url requires a non-empty default-template', $errors);
    }

    public function testEntityBoundSeoUrlWithBlankDefaultTemplateIsRejected(): void
    {
        $errors = $this->validate(
            <<<'XML'
                <seo-url name="blog-detail" entity="ce_blog">
                    <default-template>   </default-template>
                </seo-url>
                XML
        );

        static::assertStringContainsString('blog-detail: an entity bound seo-url requires a non-empty default-template', $errors);
    }

    public function testStaticSeoUrlWithDefaultTemplateIsRejected(): void
    {
        $errors = $this->validate(
            <<<'XML'
                <seo-url name="imprint">
                    <path>imprint</path>
                    <default-template>imprint</default-template>
                </seo-url>
                XML
        );

        static::assertStringContainsString('imprint: a static seo-url must not define a default-template', $errors);
    }

    public function testEmptyPathIsRejected(): void
    {
        $errors = $this->validate(
            <<<'XML'
                <seo-url name="imprint">
                    <path></path>
                </seo-url>
                XML
        );

        static::assertStringContainsString('imprint: the path for "en-GB" must not be empty', $errors);
    }

    public function testPathWithCharactersThatAreNotUrlAllowedIsRejected(): void
    {
        $errors = $this->validate(
            <<<'XML'
                <seo-url name="imprint">
                    <path lang="de-DE">impres#sum</path>
                </seo-url>
                XML
        );

        static::assertStringContainsString('imprint: the path "impres#sum" contains characters that are not allowed in URLs', $errors);
    }

    public function testPathTakenByAnExistingRouteIsRejected(): void
    {
        $this->matchingPaths = ['/account'];

        $errors = $this->validate(
            <<<'XML'
                <seo-url name="my-account">
                    <path>account</path>
                </seo-url>
                XML
        );

        static::assertStringContainsString('my-account: the path "account" is already used by another route', $errors);
    }

    private function validate(string $seoUrls): string
    {
        $manifest = Manifest::createFromXml(
            <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                    <meta>
                        <name>SwagSeoUrlTest</name>
                        <label>Swag SEO URL Test</label>
                        <description>Test for storefront SEO URLs</description>
                        <author>shopware AG</author>
                        <copyright>(c) by shopware AG</copyright>
                        <version>1.0.0</version>
                        <license>MIT</license>
                    </meta>
                    <storefront>
                        $seoUrls
                    </storefront>
                </manifest>
                XML
        );

        $errors = $this->validator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(StorefrontSeoUrlError::class, $error);

        return $error->getMessage();
    }
}
