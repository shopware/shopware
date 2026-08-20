/**
 * @sw-package framework
 */

import TemplateFactory from 'src/core/factory/template.factory';
import {
    bridgeNativeSetupExtensions,
    injectNativeBlockHosts,
    injectNativeBlockHostsForComponent,
    resetNativeBlockHosts,
} from 'src/core/factory/native-extensions-twig-bridge';
import { registerNativeExtensionTargets, resetNativeExtensionTargets } from 'src/core/factory/native-extension-targets';

/**
 * Reads the merged markup the Twig pipeline produced for one component.
 */
function renderedHtml(componentName: string): string {
    const normalizedTemplates = TemplateFactory.getNormalizedTemplateRegistry() as Map<string, { html?: string }>;

    return normalizedTemplates.get(componentName)?.html ?? '';
}

/**
 * Renders one component through the real Twig merge, exactly as `resolveComponentTemplates()` does.
 */
function renderTemplate(componentName: string): string {
    injectNativeBlockHosts();
    TemplateFactory.resolveTemplates();

    return renderedHtml(componentName);
}

describe('core/factory/native-extensions-twig-bridge.ts', () => {
    beforeEach(() => {
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.clearTwigCache();
        resetNativeBlockHosts();
        resetNativeExtensionTargets();
    });

    afterEach(() => {
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.clearTwigCache();
        resetNativeBlockHosts();
        resetNativeExtensionTargets();
    });

    it('wraps a targeted legacy block in a sw-native-block-host', () => {
        TemplateFactory.registerComponentTemplate(
            'sw-legacy',
            '{% block sw_legacy_content %}<div class="content"></div>{% endblock %}',
        );
        registerNativeExtensionTargets({
            component: 'sw-legacy',
            blocks: ['sw_legacy_content'],
        });

        const html = renderTemplate('sw-legacy');

        expect(html).toContain('<sw-native-block-host name="sw_legacy_content" :data="$dataScope">');
        expect(html).toContain('<template #parent><div class="content"></div></template>');
    });

    it('leaves untargeted blocks untouched', () => {
        TemplateFactory.registerComponentTemplate(
            'sw-legacy',
            '{% block sw_legacy_content %}<div class="content"></div>{% endblock %}',
        );

        const html = renderTemplate('sw-legacy');

        expect(html).not.toContain('sw-native-block-host');
        expect(html).toContain('<div class="content"></div>');
    });

    it('merges the wrapper after every registered Twig override', () => {
        TemplateFactory.registerComponentTemplate(
            'sw-legacy',
            '{% block sw_legacy_content %}<div class="base"></div>{% endblock %}',
        );
        TemplateFactory.registerTemplateOverride(
            'sw-legacy',
            '{% block sw_legacy_content %}{% parent %}<div class="plugin"></div>{% endblock %}',
            0,
        );
        registerNativeExtensionTargets({
            component: 'sw-legacy',
            blocks: ['sw_legacy_content'],
        });

        const html = renderTemplate('sw-legacy');

        // Both the default content and the legacy plugin override end up inside the wrapper's parent
        // slot, which is what keeps the stacking order default -> Twig override -> native extension.
        expect(html).toContain('<template #parent><div class="base"></div><div class="plugin"></div></template>');
    });

    it('wraps a nested block independently of its parent block', () => {
        TemplateFactory.registerComponentTemplate(
            'sw-legacy',
            '{% block sw_legacy_outer %}<div>{% block sw_legacy_inner %}<span></span>{% endblock %}</div>{% endblock %}',
        );
        registerNativeExtensionTargets({
            component: 'sw-legacy',
            blocks: ['sw_legacy_inner'],
        });

        const html = renderTemplate('sw-legacy');

        expect(html).toContain('<sw-native-block-host name="sw_legacy_inner" :data="$dataScope">');
        expect(html).not.toContain('name="sw_legacy_outer"');
    });

    it('skips a block whose conditional chain crosses the block boundary and reports it', () => {
        jest.spyOn(console, 'error').mockImplementation(() => {});
        TemplateFactory.registerComponentTemplate(
            'sw-legacy',
            '{% block sw_legacy_first %}<div v-if="a"></div>{% endblock %}{% block sw_legacy_second %}<div v-else></div>{% endblock %}',
        );
        registerNativeExtensionTargets({
            component: 'sw-legacy',
            blocks: ['sw_legacy_first'],
        });

        const html = renderTemplate('sw-legacy');

        expect(html).not.toContain('sw-native-block-host');
        expect(console.error).toHaveBeenCalledWith(
            expect.stringContaining('takes part in a v-if/v-else chain that crosses its block boundary'),
        );
    });

    it('registers a wrapper for every component declaring the targeted block', () => {
        TemplateFactory.registerComponentTemplate('sw-base', '{% block shared %}<div class="base"></div>{% endblock %}');
        TemplateFactory.extendComponentTemplate(
            'sw-child',
            'sw-base',
            '{% block shared %}{% parent %}<div class="child"></div>{% endblock %}',
        );
        registerNativeExtensionTargets({
            component: 'sw-base',
            blocks: ['shared'],
        });

        injectNativeBlockHosts();
        TemplateFactory.resolveTemplates();

        expect(renderedHtml('sw-base')).toContain('<sw-native-block-host name="shared"');
        // The child redeclares the block, so it gets its own wrapper; the inherited inner one stays in
        // the parent slot and is neutralised at runtime by the host's nesting guard.
        expect(renderedHtml('sw-child')).toContain('<sw-native-block-host name="shared"');
    });

    it('registers a wrapper on the extended component when only the child is built', () => {
        TemplateFactory.registerComponentTemplate('sw-base', '{% block shared %}<div class="base"></div>{% endblock %}');
        TemplateFactory.extendComponentTemplate('sw-child', 'sw-base', '');
        registerNativeExtensionTargets({
            component: 'sw-base',
            blocks: ['shared'],
        });

        injectNativeBlockHostsForComponent('sw-child');
        TemplateFactory.resolveTemplates();

        expect(renderedHtml('sw-child')).toContain('<sw-native-block-host name="shared"');
    });

    it('does not parse a template that mentions no targeted block', () => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        // Unparsable on purpose: reaching the parser at all would surface as a warning.
        TemplateFactory.registerComponentTemplate('sw-legacy', '{% block sw_other_content %}');
        registerNativeExtensionTargets({
            component: 'sw-legacy',
            blocks: ['sw_legacy_content'],
        });

        injectNativeBlockHosts();

        expect(console.warn).not.toHaveBeenCalled();
        expect(TemplateFactory.getTemplateOverrides('sw-legacy')).toHaveLength(0);
    });

    it('registers the wrapper only once across repeated resolve runs', () => {
        TemplateFactory.registerComponentTemplate(
            'sw-legacy',
            '{% block sw_legacy_content %}<div class="content"></div>{% endblock %}',
        );
        registerNativeExtensionTargets({
            component: 'sw-legacy',
            blocks: ['sw_legacy_content'],
        });

        injectNativeBlockHosts();
        injectNativeBlockHosts();

        expect(TemplateFactory.getTemplateOverrides('sw-legacy')).toHaveLength(1);
    });

    describe('bridgeNativeSetupExtensions', () => {
        it('converts an Options API base that a native override targets', () => {
            registerNativeExtensionTargets({ component: 'sw-legacy' });

            const config = bridgeNativeSetupExtensions('sw-legacy', {
                template: '<div></div>',
                data: () => ({ count: 1 }),
            });

            expect(typeof config.setup).toBe('function');
            expect(config.data).toBeUndefined();
        });

        it('leaves an untargeted component untouched', () => {
            const original = {
                template: '<div></div>',
                data: () => ({ count: 1 }),
            };

            expect(bridgeNativeSetupExtensions('sw-legacy', original)).toBe(original);
        });

        it('leaves a component that already has a setup function untouched', () => {
            registerNativeExtensionTargets({ component: 'sw-legacy' });

            const original = {
                template: '<div></div>',
                setup: () => ({}),
            };

            expect(bridgeNativeSetupExtensions('sw-legacy', original)).toBe(original);
        });

        it('reports why a component with an extends chain is skipped', () => {
            jest.spyOn(console, 'error').mockImplementation(() => {});
            registerNativeExtensionTargets({ component: 'sw-legacy' });

            const original = {
                template: '<div></div>',
                extends: { name: 'sw-base' },
                data: () => ({ count: 1 }),
            };

            expect(bridgeNativeSetupExtensions('sw-legacy', original)).toBe(original);
            expect(console.error).toHaveBeenCalledWith(expect.stringContaining('cannot be made extendable'));
        });

        it('falls back to the unconverted config when the conversion throws', () => {
            jest.spyOn(console, 'error').mockImplementation(() => {});
            registerNativeExtensionTargets({ component: 'sw-legacy' });

            const original = {
                template: '<div></div>',
                get mixins(): never {
                    throw new Error('broken mixin accessor');
                },
            } as unknown as Parameters<typeof bridgeNativeSetupExtensions>[1];

            expect(bridgeNativeSetupExtensions('sw-legacy', original)).toBe(original);
            expect(console.error).toHaveBeenCalledWith(
                expect.stringContaining('into an extendable setup'),
                expect.any(Error),
            );
        });
    });
});
