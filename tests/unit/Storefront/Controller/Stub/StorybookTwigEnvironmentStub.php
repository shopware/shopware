<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller\Stub;

use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TemplateWrapper;

/**
 * @internal
 *
 * A test-specific Twig Environment that avoids mocking the final TemplateWrapper class.
 */
class StorybookTwigEnvironmentStub extends Environment
{
    public string $renderOutput = '';

    public ?\Throwable $renderException = null;

    public ?\Throwable $createTemplateException = null;

    /**
     * @var \Closure(string|TemplateWrapper, array<string, mixed>): string|null
     */
    public ?\Closure $renderCallback = null;

    /**
     * @var array<string, mixed>
     */
    public array $globals = [];

    /**
     * @var array<string, mixed>
     */
    public array $renderContext = [];

    public function __construct()
    {
        parent::__construct(new ArrayLoader([]));
    }

    public function addGlobal(string $name, mixed $value): void
    {
        $this->globals[$name] = $value;
    }

    public function createTemplate(string $template, ?string $name = null): TemplateWrapper
    {
        if ($this->createTemplateException !== null) {
            throw $this->createTemplateException;
        }

        return parent::createTemplate('');
    }

    /**
     * @param string|TemplateWrapper $name
     * @param array<string, mixed> $context
     */
    public function render($name, array $context = []): string
    {
        $this->renderContext = $context;

        if ($this->renderException !== null) {
            throw $this->renderException;
        }

        if ($this->renderCallback !== null) {
            return ($this->renderCallback)($name, $context);
        }

        return $this->renderOutput;
    }
}
