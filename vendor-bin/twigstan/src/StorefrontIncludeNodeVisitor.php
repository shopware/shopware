<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Shopware\Storefront\Framework\Captcha\HoneypotCaptcha;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\IncludeNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class StorefrontIncludeNodeVisitor implements NodeVisitorInterface
{
    /**
     * @var array<string, string|null>
     */
    private array $contracts = [];

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
        $templates = $this->getTemplates($expression);
        if ($templates === []) {
            if ($expression instanceof ConstantExpression && \is_string($expression->getAttribute('value'))) {
                $template = $expression->getAttribute('value');
                $type = $this->getContract($env, $template);
                if ($type !== null) {
                    $variables = $node->hasNode('variables') ? $node->getNode('variables') : new ArrayExpression([], $node->getTemplateLine());
                    \assert($variables instanceof AbstractExpression);
                    $node->setNode('variables', new TemplateContractContextExpression($variables, !$node->getAttribute('only'), $template, $type, $node->getTemplateLine()));
                    $node->setAttribute('only', true);
                }
            }

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

    private function getContract(Environment $twig, string $template): ?string
    {
        if (\array_key_exists($template, $this->contracts)) {
            return $this->contracts[$template];
        }
        try {
            $declaration = TemplateContracts::declaration($twig, $twig->getLoader()->getSourceContext($template));
        } catch (LoaderError) {
            return $this->contracts[$template] = null;
        }
        if ($declaration === null) {
            return $this->contracts[$template] = null;
        }
        $fields = [];
        foreach ($declaration as $name => $field) {
            $fields[] = $name . ($field['optional'] ? '?' : '') . ': ' . $field['type'];
        }

        return $this->contracts[$template] = 'array{' . implode(', ', $fields) . '}';
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
