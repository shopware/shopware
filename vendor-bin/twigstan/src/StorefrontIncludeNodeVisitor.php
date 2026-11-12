<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Shopware\Storefront\Framework\Captcha\HoneypotCaptcha;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\IncludeNode;
use Twig\Node\Node;
use Twig\Node\TypesNode;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class StorefrontIncludeNodeVisitor implements NodeVisitorInterface
{
    public function __construct(private readonly string $viewsDirectory)
    {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof IncludeNode) {
            return $node;
        }

        $expression = $node->getNode('expr');
        if ($expression instanceof ConstantExpression
            && $expression->getAttribute('value') === '@Storefront/storefront/component/line-item/line-item.html.twig'
            && $node->getSourceContext()?->getPath() === $this->viewsDirectory . '/storefront/component/line-item/element/children-wrapper.html.twig'
        ) {
            // shortcut: Widen the recursive counter so TwigStan converges; remove when it widens recursive scalar types upstream.
            // Inject the hint during analysis because Ludtwig 0.14 cannot parse Twig's types tag.
            return new Node([
                new TypesNode(['nestingLevel' => ['type' => 'int', 'optional' => false]], $node->getTemplateLine()),
                $node,
            ], [], $node->getTemplateLine());
        }

        $templates = $this->getTemplates($expression);
        if ($templates === []) {
            return $node;
        }

        $include = clone $node;
        $include->setNode('expr', new TemplateNamesExpression($templates, $expression->getTemplateLine()));

        // Retain the dynamic include for targets supplied by extensions outside the scanned core templates.
        return new Node([$include, $node], [], $node->getTemplateLine());
    }

    public function getPriority(): int
    {
        // Expand before TwigStan replaces IncludeNode instances with its analysis nodes.
        return -10;
    }

    /**
     * @return list<string>
     */
    private function getTemplates(Node $expression): array
    {
        if ($expression instanceof FilterExpression
            && $expression->getAttribute('name') === 'format'
            && $expression->getNode('node') instanceof ConstantExpression
            && $expression->getNode('node')->getAttribute('value') === '@Storefront/storefront/component/captcha/%s.html.twig'
        ) {
            return array_map(
                static fn (string $name): string => '@Storefront/storefront/component/captcha/' . $name . '.html.twig',
                [BasicCaptcha::CAPTCHA_NAME, GoogleReCaptchaV2::CAPTCHA_NAME, GoogleReCaptchaV3::CAPTCHA_NAME, HoneypotCaptcha::CAPTCHA_NAME],
            );
        }

        if (!$expression instanceof ConcatBinary
            || !$expression->getNode('right') instanceof ConstantExpression
            || $expression->getNode('right')->getAttribute('value') !== '.html.twig'
        ) {
            return [];
        }

        $left = $expression->getNode('left');
        if (!$left instanceof ConcatBinary || !$left->getNode('left') instanceof ConstantExpression) {
            return [];
        }

        $prefix = $left->getNode('left')->getAttribute('value');
        if (!\in_array($prefix, [
            '@Storefront/storefront/section/cms-section-',
            '@Storefront/storefront/block/cms-block-',
            '@Storefront/storefront/element/cms-element-',
        ], true)) {
            return [];
        }

        $relativePrefix = substr($prefix, \strlen('@Storefront/'));
        $directory = \dirname($relativePrefix);
        $templates = [];
        foreach ((new Finder())->files()->in($this->viewsDirectory . '/' . $directory)->depth('== 0')->name(basename($relativePrefix) . '*.html.twig')->sortByName() as $file) {
            $templates[] = '@Storefront/' . $directory . '/' . $file->getFilename();
        }

        return $templates;
    }
}
