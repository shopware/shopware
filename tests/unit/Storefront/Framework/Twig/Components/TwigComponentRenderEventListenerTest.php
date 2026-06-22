<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Components;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentRenderEventListener;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\ComponentMetadata;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;
use Symfony\UX\TwigComponent\MountedComponent;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[CoversClass(TwigComponentRenderEventListener::class)]
class TwigComponentRenderEventListenerTest extends TestCase
{
    public function testInvokeAddsComponentNameInProductionEnvironment(): void
    {
        $listener = new TwigComponentRenderEventListener('prod');

        $attributes = $this->createComponentAttributes(['class' => 'test-class']);
        $variables = ['attributes' => $attributes];

        $metadata = new ComponentMetadata([
            'key' => 'Button:Primary',
            'template' => 'components/Button/Primary.html.twig',
            'class' => 'App\\Component\\Button',
            'service_id' => 'app.component.button',
        ]);

        $mountedComponent = $this->createMountedComponent($metadata);
        $event = $this->createPreRenderEvent($mountedComponent, $metadata, $variables);

        $listener($event);

        $updatedVars = $event->getVariables();
        $attrs = $updatedVars['attributes'];
        static::assertInstanceOf(ComponentAttributes::class, $attrs);

        $rendered = (string) $attrs;
        static::assertStringContainsString('data-component-name="Button:Primary"', $rendered);
        static::assertStringContainsString('class="test-class"', $rendered);
        static::assertStringNotContainsString('data-component-template', $rendered);
        static::assertStringNotContainsString('data-component-parent', $rendered);
    }

    public function testInvokeAddsDebugAttributesInDevEnvironment(): void
    {
        $listener = new TwigComponentRenderEventListener('dev');

        $attributes = $this->createComponentAttributes(['class' => 'test-class']);
        $variables = ['attributes' => $attributes];

        $metadata = new ComponentMetadata([
            'key' => 'Button:Primary',
            'template' => 'components/Button/Primary.html.twig',
            'class' => 'App\\Component\\Button',
            'service_id' => 'app.component.button',
        ]);

        $mountedComponent = $this->createMountedComponent($metadata);
        $event = $this->createPreRenderEvent($mountedComponent, $metadata, $variables);

        $listener($event);

        $updatedVars = $event->getVariables();
        $attrs = $updatedVars['attributes'];
        static::assertInstanceOf(ComponentAttributes::class, $attrs);

        $rendered = (string) $attrs;
        static::assertStringContainsString('data-component-name="Button:Primary"', $rendered);
        static::assertStringContainsString('data-component-template="components/Button/Primary.html.twig"', $rendered);
        static::assertStringContainsString('class="test-class"', $rendered);
    }

    public function testInvokeAddsParentTemplateInDevEnvironment(): void
    {
        $listener = new TwigComponentRenderEventListener('dev');

        $attributes = $this->createComponentAttributes([]);
        $variables = ['attributes' => $attributes];

        $metadata = new ComponentMetadata([
            'key' => 'Button:Primary',
            'template' => 'components/Button/Primary.html.twig',
            'class' => 'App\\Component\\Button',
            'service_id' => 'app.component.button',
        ]);

        $mountedComponent = $this->createMountedComponent($metadata, [
            'hostTemplate' => 'components/Sw/Filter/Panel.html.twig',
        ]);
        $event = $this->createPreRenderEvent($mountedComponent, $metadata, $variables);

        $listener($event);

        $updatedVars = $event->getVariables();
        $attrs = $updatedVars['attributes'];
        static::assertInstanceOf(ComponentAttributes::class, $attrs);

        $rendered = (string) $attrs;
        static::assertStringContainsString('data-component-name="Button:Primary"', $rendered);
        static::assertStringContainsString('data-component-template="components/Button/Primary.html.twig"', $rendered);
        static::assertStringContainsString('data-component-parent="Sw:Filter:Panel"', $rendered);
        static::assertStringContainsString('data-component-parent-template="components/Sw/Filter/Panel.html.twig"', $rendered);
    }

    public function testInvokeDoesNotAddParentTemplateInProductionEnvironment(): void
    {
        $listener = new TwigComponentRenderEventListener('prod');

        $attributes = $this->createComponentAttributes([]);
        $variables = ['attributes' => $attributes];

        $metadata = new ComponentMetadata([
            'key' => 'Button:Primary',
            'template' => 'components/Button/Primary.html.twig',
            'class' => 'App\\Component\\Button',
            'service_id' => 'app.component.button',
        ]);

        $mountedComponent = $this->createMountedComponent($metadata, [
            'hostTemplate' => 'components/Sw/Filter/Panel.html.twig',
        ]);
        $event = $this->createPreRenderEvent($mountedComponent, $metadata, $variables);

        $listener($event);

        $updatedVars = $event->getVariables();
        $attrs = $updatedVars['attributes'];
        static::assertInstanceOf(ComponentAttributes::class, $attrs);

        $rendered = (string) $attrs;
        static::assertStringContainsString('data-component-name="Button:Primary"', $rendered);
        static::assertStringNotContainsString('data-component-template', $rendered);
        static::assertStringNotContainsString('data-component-parent', $rendered);
    }

    public function testInvokeDoesNothingWhenAttributesVariableNotPresent(): void
    {
        $listener = new TwigComponentRenderEventListener('dev');

        $variables = ['someOtherVar' => 'value'];

        $metadata = new ComponentMetadata([
            'key' => 'Button:Primary',
            'template' => 'components/Button/Primary.html.twig',
            'class' => 'App\\Component\\Button',
            'service_id' => 'app.component.button',
        ]);

        $mountedComponent = $this->createMountedComponent($metadata);
        $event = $this->createPreRenderEvent($mountedComponent, $metadata, $variables);

        $listener($event);

        static::assertSame($variables, $event->getVariables());
    }

    public function testInvokeDoesNothingWhenAttributesIsNotComponentAttributes(): void
    {
        $listener = new TwigComponentRenderEventListener('dev');

        $variables = ['attributes' => 'not a ComponentAttributes object'];

        $metadata = new ComponentMetadata([
            'key' => 'Button:Primary',
            'template' => 'components/Button/Primary.html.twig',
            'class' => 'App\\Component\\Button',
            'service_id' => 'app.component.button',
        ]);

        $mountedComponent = $this->createMountedComponent($metadata);
        $event = $this->createPreRenderEvent($mountedComponent, $metadata, $variables);

        $listener($event);

        static::assertSame($variables, $event->getVariables());
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function pathToComponentNameDataProvider(): \Generator
    {
        yield 'simple path' => [
            'components/Button.html.twig',
            'Button',
        ];

        yield 'nested path' => [
            'components/Sw/Filter/Panel.html.twig',
            'Sw:Filter:Panel',
        ];

        yield 'path without components prefix' => [
            'Sw/Filter/Panel.html.twig',
            'Sw:Filter:Panel',
        ];

        yield 'deeply nested path' => [
            'components/Sw/Forms/Input/Text/Primary.html.twig',
            'Sw:Forms:Input:Text:Primary',
        ];

        yield 'single component' => [
            'components/Button.html.twig',
            'Button',
        ];
    }

    #[DataProvider('pathToComponentNameDataProvider')]
    public function testPathToComponentNameConversion(string $path, string $expectedName): void
    {
        $listener = new TwigComponentRenderEventListener('dev');

        $attributes = $this->createComponentAttributes([]);
        $variables = ['attributes' => $attributes];

        $metadata = new ComponentMetadata([
            'key' => 'SomeComponent',
            'template' => 'components/SomeComponent.html.twig',
            'class' => 'App\\Component\\SomeComponent',
            'service_id' => 'app.component.some_component',
        ]);

        $mountedComponent = $this->createMountedComponent($metadata, [
            'hostTemplate' => $path,
        ]);
        $event = $this->createPreRenderEvent($mountedComponent, $metadata, $variables);

        $listener($event);

        $updatedVars = $event->getVariables();
        $attrs = $updatedVars['attributes'];
        $rendered = (string) $attrs;

        static::assertStringContainsString('data-component-parent="' . $expectedName . '"', $rendered);
    }

    public function testInvokeWithCustomAttributesVariable(): void
    {
        $listener = new TwigComponentRenderEventListener('prod');

        $attributes = $this->createComponentAttributes(['id' => 'my-component']);
        $variables = ['customAttrs' => $attributes];

        $metadata = new ComponentMetadata([
            'key' => 'CustomComponent',
            'template' => 'components/CustomComponent.html.twig',
            'class' => 'App\\Component\\CustomComponent',
            'service_id' => 'app.component.custom_component',
            'attributes_var' => 'customAttrs',
        ]);

        $mountedComponent = $this->createMountedComponent($metadata);
        $event = $this->createPreRenderEvent($mountedComponent, $metadata, $variables);

        $listener($event);

        $updatedVars = $event->getVariables();
        static::assertArrayHasKey('customAttrs', $updatedVars);

        $attrs = $updatedVars['customAttrs'];
        static::assertInstanceOf(ComponentAttributes::class, $attrs);

        $rendered = (string) $attrs;
        static::assertStringContainsString('data-component-name="CustomComponent"', $rendered);
        static::assertStringContainsString('id="my-component"', $rendered);
    }

    public function testAttributesDefaultsPreservesExistingAttributes(): void
    {
        $listener = new TwigComponentRenderEventListener('dev');

        $attributes = $this->createComponentAttributes([
            'class' => 'btn btn-primary',
            'data-test' => 'existing',
            'id' => 'my-button',
        ]);
        $variables = ['attributes' => $attributes];

        $metadata = new ComponentMetadata([
            'key' => 'Button',
            'template' => 'components/Button.html.twig',
            'class' => 'App\\Component\\Button',
            'service_id' => 'app.component.button',
        ]);

        $mountedComponent = $this->createMountedComponent($metadata);
        $event = $this->createPreRenderEvent($mountedComponent, $metadata, $variables);

        $listener($event);

        $updatedVars = $event->getVariables();
        $attrs = $updatedVars['attributes'];
        $rendered = (string) $attrs;

        static::assertStringContainsString('class="btn btn-primary"', $rendered);
        static::assertStringContainsString('data-test="existing"', $rendered);
        static::assertStringContainsString('id="my-button"', $rendered);
        static::assertStringContainsString('data-component-name="Button"', $rendered);
        static::assertStringContainsString('data-component-template="components/Button.html.twig"', $rendered);
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    private function createComponentAttributes(array $attributes = []): ComponentAttributes
    {
        // EscaperRuntime is final, so we create a real instance
        $escaper = new EscaperRuntime('UTF-8');

        return new ComponentAttributes($attributes, $escaper);
    }

    /**
     * @param array<string, mixed> $extraMetadata
     */
    private function createMountedComponent(ComponentMetadata $metadata, array $extraMetadata = []): MountedComponent
    {
        $component = new \stdClass();
        $attributes = $this->createComponentAttributes();

        return new MountedComponent(
            $metadata->getName(),
            $component,
            $attributes,
            [],
            $extraMetadata
        );
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function createPreRenderEvent(MountedComponent $mountedComponent, ComponentMetadata $metadata, array $variables): PreRenderEvent
    {
        return new PreRenderEvent($mountedComponent, $metadata, $variables);
    }
}
