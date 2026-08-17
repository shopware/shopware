/**
 * @sw-package framework
 */

import TemplateFactory from 'src/core/factory/template.factory';
import { registerNativeBlockOverrides, resetNativeBlockOverrides } from 'src/core/factory/native-block-override-registry';

/**
 * Renders a Twig component template through the full TemplateFactory pipeline.
 * Use it to assert on the markup a component would actually receive.
 *
 * @example
 * const html = renderTemplate('sw-demo', '{% block sw_demo %}<div />{% endblock %}');
 */
function renderTemplate(name: string, template: string, overrides: string[] = []): string {
    TemplateFactory.registerComponentTemplate(name, template);
    overrides.forEach((override, index) => TemplateFactory.registerTemplateOverride(name, override, index));

    return TemplateFactory.getRenderedTemplate(name) as unknown as string;
}

describe('core/factory/emit-legacy-block-wrappers.ts', () => {
    beforeEach(() => {
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.disableTwigCache();
        resetNativeBlockOverrides();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('leaves a template untouched when no native override targets it', () => {
        const html = renderTemplate('sw-demo', '{% block sw_demo_content %}<div class="content"></div>{% endblock %}');

        expect(html).not.toContain('<sw-block');
        expect(html).toContain('<div class="content"></div>');
    });

    it('materializes a targeted block boundary as a sw-block element', () => {
        registerNativeBlockOverrides(['sw_demo_content']);

        const html = renderTemplate('sw-demo', '{% block sw_demo_content %}<div class="content"></div>{% endblock %}');

        expect(html).toContain('<sw-block name="sw_demo_content" from-twig-template :data="$dataScope">');
        expect(html).toContain('<div class="content"></div></sw-block>');
    });

    it('wraps only the targeted block and leaves its siblings as plain markup', () => {
        registerNativeBlockOverrides(['sw_demo_second']);

        const html = renderTemplate(
            'sw-demo',
            `{% block sw_demo_first %}<div class="first"></div>{% endblock %}
             {% block sw_demo_second %}<div class="second"></div>{% endblock %}`,
        );

        expect(html).toContain('<sw-block name="sw_demo_second"');
        expect(html).not.toContain('name="sw_demo_first"');
        expect(html).toContain('<div class="first"></div>');
    });

    it('wraps a nested block inside an unwrapped parent block', () => {
        registerNativeBlockOverrides(['sw_demo_inner']);

        const html = renderTemplate(
            'sw-demo',
            `{% block sw_demo_outer %}<div class="outer">
                {% block sw_demo_inner %}<div class="inner"></div>{% endblock %}
            </div>{% endblock %}`,
        );

        expect(html).toContain('<div class="outer">');
        expect(html).toContain('<sw-block name="sw_demo_inner"');
        expect(html).not.toContain('name="sw_demo_outer"');
    });

    it('keeps a legacy Twig override inside the wrapper it was merged into', () => {
        registerNativeBlockOverrides(['sw_demo_content']);

        const html = renderTemplate('sw-demo', '{% block sw_demo_content %}<div class="content"></div>{% endblock %}', [
            '{% block sw_demo_content %}{% parent %}<div class="from-twig-override"></div>{% endblock %}',
        ]);

        const wrapperContent = html.substring(html.indexOf('<sw-block'), html.indexOf('</sw-block>'));

        expect(wrapperContent).toContain('<div class="content"></div>');
        expect(wrapperContent).toContain('<div class="from-twig-override"></div>');
    });

    it('drops an unresolved parent placeholder, like the plain Twig render does', () => {
        registerNativeBlockOverrides(['sw_demo_content']);

        const html = renderTemplate(
            'sw-demo',
            '{% block sw_demo_content %}{% parent %}<div class="content"></div>{% endblock %}',
        );

        expect(html).not.toContain('PARENT');
        expect(html).toContain('<div class="content"></div>');
    });

    describe('structural rejections', () => {
        it('refuses to wrap a block that starts with a named slot template', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            registerNativeBlockOverrides(['sw_demo_slot']);

            const html = renderTemplate(
                'sw-demo',
                '{% block sw_demo_slot %}<template #footer><div class="footer"></div></template>{% endblock %}',
            );

            expect(html).not.toContain('<sw-block');
            expect(warn).toHaveBeenCalledWith(expect.stringContaining('sw_demo_slot'));
            expect(warn).toHaveBeenCalledWith(expect.stringContaining('named slot template'));
        });

        it('refuses to wrap a block that starts with a v-else branch', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            registerNativeBlockOverrides(['sw_demo_else']);

            const html = renderTemplate(
                'sw-demo',
                `<div v-if="condition"></div>
                 {% block sw_demo_else %}<div v-else class="else-branch"></div>{% endblock %}`,
            );

            expect(html).not.toContain('<sw-block');
            expect(warn).toHaveBeenCalledWith(expect.stringContaining('v-else branch whose v-if lives outside'));
        });

        it('refuses to wrap a block that is directly followed by a v-else branch', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            registerNativeBlockOverrides(['sw_demo_if']);

            const html = renderTemplate(
                'sw-demo',
                `{% block sw_demo_if %}<div v-if="condition" class="if-branch"></div>{% endblock %}
                 <div v-else class="else-branch"></div>`,
            );

            expect(html).not.toContain('<sw-block');
            expect(warn).toHaveBeenCalledWith(expect.stringContaining('directly followed by a v-else branch'));
        });

        it('still wraps a block whose content merely contains a v-if', () => {
            registerNativeBlockOverrides(['sw_demo_wrapped_if']);

            const html = renderTemplate(
                'sw-demo',
                '{% block sw_demo_wrapped_if %}<div class="card"><span v-if="condition"></span></div>{% endblock %}',
            );

            expect(html).toContain('<sw-block name="sw_demo_wrapped_if"');
        });

        it('wraps an empty block so it can be filled by an override', () => {
            registerNativeBlockOverrides(['sw_demo_empty']);

            const html = renderTemplate('sw-demo', '{% block sw_demo_empty %}{% endblock %}');

            expect(html).toContain('<sw-block name="sw_demo_empty" from-twig-template :data="$dataScope"></sw-block>');
        });
    });
});
