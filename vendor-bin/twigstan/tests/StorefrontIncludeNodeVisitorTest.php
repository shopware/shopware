<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Node\IncludeNode;
use Twig\Source;
use TwigStan\Processing\Compilation\TwigVisitor\ReplaceIncludeNodeNodeVisitor;
use TwigStan\Processing\Compilation\TwigVisitor\SimpleIncludeNode;
use TwigStan\Twig\Node\NodeFinder;

/**
 * @internal
 */
#[Package('framework')]
class StorefrontIncludeNodeVisitorTest extends TestCase
{
    public function testExpandsEachCmsLevelAndPreservesIncludeArguments(): void
    {
        $directory = __DIR__ . '/fixtures/cms';
        $loader = new FilesystemLoader();
        $loader->addPath($directory, 'Storefront');
        $twig = new Environment($loader);
        $twig->addNodeVisitor(new StorefrontIncludeNodeVisitor($directory));
        $twig->addNodeVisitor(new ReplaceIncludeNodeNodeVisitor());

        foreach (['section' => ['default'], 'block' => ['text'], 'element' => ['image', 'text']] as $level => $types) {
            $source = '{% include \'@Storefront/storefront/' . $level . '/cms-' . $level . '-\' ~ item.type ~ \'.html.twig\' ignore missing with {value: item} only %}';
            $module = $twig->parse($twig->tokenize(new Source($source, 'test-' . $level)));
            $includes = (new NodeFinder())->findInstanceOf($module, IncludeNode::class);

            static::assertCount(2, $includes);
            $include = $includes[0];
            static::assertInstanceOf(SimpleIncludeNode::class, $include);
            $expression = $include->getNode('expr');
            static::assertInstanceOf(TemplateNamesExpression::class, $expression);
            static::assertSame(array_map(static fn (string $type): string => '@Storefront/storefront/' . $level . '/cms-' . $level . '-' . $type . '.html.twig', $types), $expression->getAttribute('templates'));
            static::assertTrue($include->getAttribute('only'));
            static::assertTrue($include->getAttribute('ignore_missing'));
            static::assertTrue($include->hasNode('variables'));
        }
    }

    public function testLeavesStaticAndUnrelatedDynamicIncludesUnchanged(): void
    {
        $twig = new Environment(new FilesystemLoader());
        $twig->addNodeVisitor(new StorefrontIncludeNodeVisitor(__DIR__ . '/fixtures/cms'));
        $source = '{% include \'static.html.twig\' %}{% include \'custom-\' ~ name ~ \'.html.twig\' %}{% include \'custom-%s.html.twig\'|format(name) %}';
        $module = $twig->parse($twig->tokenize(new Source($source, 'test')));

        static::assertCount(3, (new NodeFinder())->findInstanceOf($module, IncludeNode::class));
    }

    public function testExpandsConfiguredCaptchaNamesAndPreservesContext(): void
    {
        $twig = new Environment(new FilesystemLoader());
        $twig->addNodeVisitor(new StorefrontIncludeNodeVisitor(__DIR__ . '/fixtures/cms'));
        $twig->addNodeVisitor(new ReplaceIncludeNodeNodeVisitor());
        $source = '{% include \'@Storefront/storefront/component/captcha/%s.html.twig\'|format(captchaKey) ignore missing with {formId: formId} only %}';
        $module = $twig->parse($twig->tokenize(new Source($source, 'captcha')));
        $includes = (new NodeFinder())->findInstanceOf($module, IncludeNode::class);

        static::assertCount(2, $includes);
        $include = $includes[0];
        static::assertInstanceOf(SimpleIncludeNode::class, $include);
        static::assertInstanceOf(TemplateNamesExpression::class, $include->getNode('expr'));
        static::assertSame([
            '@Storefront/storefront/component/captcha/basicCaptcha.html.twig',
            '@Storefront/storefront/component/captcha/googleReCaptchaV2.html.twig',
            '@Storefront/storefront/component/captcha/googleReCaptchaV3.html.twig',
            '@Storefront/storefront/component/captcha/honeypot.html.twig',
        ], $include->getNode('expr')->getAttribute('templates'));
        static::assertTrue($include->getAttribute('only'));
        static::assertTrue($include->getAttribute('ignore_missing'));
        static::assertTrue($include->hasNode('variables'));
    }
}
