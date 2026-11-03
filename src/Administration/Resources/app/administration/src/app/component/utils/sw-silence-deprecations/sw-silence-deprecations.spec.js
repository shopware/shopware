/**
 * @sw-package framework
 */
import { flushPromises, mount } from '@vue/test-utils';
import { deprecations, useProductionDeprecationGuard } from 'test/_helper_/deprecations';

let scope;

const deprecatedChild = {
    name: 'sw-silence-deprecations-test-child',
    template: '<div class="child"><slot /></div>',
    created() {
        Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'sw-silence-deprecations-test-child is deprecated.');
    },
};

const otherDeprecatedChild = {
    name: 'sw-silence-deprecations-test-other',
    template: '<div class="other" />',
    created() {
        Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'sw-silence-deprecations-test-other is deprecated.');
    },
};

const components = {
    'sw-silence-deprecations-test-child': deprecatedChild,
    'sw-silence-deprecations-test-other': otherDeprecatedChild,
};

function mountTemplate(template, extraComponents = {}, data = {}) {
    return mount({
        components: { 'sw-silence-deprecations': scope, ...components, ...extraComponents },
        data: () => data,
        template,
    });
}

/** A wrapper like `sw-popover`, which silences its deprecated implementation and passes its slot through. */
function wrapperComponent() {
    return {
        name: 'sw-silence-deprecations-test-wrapper',
        components: { 'sw-silence-deprecations': scope, ...components },
        template: `
            <sw-silence-deprecations flag="V6_9_0_0">
                <sw-silence-deprecations-test-child><slot /></sw-silence-deprecations-test-child>
            </sw-silence-deprecations>
        `,
    };
}

/**
 * Builds a component through the twig block system, so overrides of its blocks apply like in an extension.
 */
async function buildWithOverride(name, template, overrideTemplate, extraComponents = {}) {
    Shopware.Component.register(name, {
        components: { 'sw-silence-deprecations': scope, ...components, ...extraComponents },
        template,
    });
    Shopware.Component.override(name, { template: overrideTemplate });

    return await Shopware.Component.build(name);
}

describe('src/app/component/utils/sw-silence-deprecations', () => {
    beforeAll(async () => {
        scope = await wrapTestComponent('sw-silence-deprecations', { sync: true });
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('renders its content without HTML of its own', () => {
        const wrapper = mountTemplate(
            '<sw-silence-deprecations flag="V6_9_0_0"><span class="first" /><span class="second" /></sw-silence-deprecations>',
        );

        expect(wrapper.html()).toBe('<span class="first"></span>\n<span class="second"></span>');
    });

    it('silences the components directly inside of it, with plain elements in between', () => {
        mountTemplate(`
            <sw-silence-deprecations flag="V6_9_0_0">
                <sw-silence-deprecations-test-child />
                <div><sw-silence-deprecations-test-other /></div>
            </sw-silence-deprecations>
        `);

        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-other is deprecated.');
    });

    it('takes the major in the dotted spelling too', () => {
        mountTemplate(`
            <sw-silence-deprecations flag="v6.9.0.0">
                <sw-silence-deprecations-test-child />
            </sw-silence-deprecations>
        `);

        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
    });

    it('silences a component inside of it on a v-if', () => {
        mountTemplate(
            `
            <sw-silence-deprecations v-if="legacy" flag="V6_9_0_0">
                <sw-silence-deprecations-test-child />
            </sw-silence-deprecations>
        `,
            {},
            { legacy: true },
        );

        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
    });

    it('silences a component inside of it on a <template v-if>, also after toggling it', async () => {
        const wrapper = mountTemplate(
            `
            <sw-silence-deprecations flag="V6_9_0_0">
                <template v-if="shown">
                    <sw-silence-deprecations-test-child />
                </template>
            </sw-silence-deprecations>
        `,
            {},
            { shown: false },
        );

        await wrapper.setData({ shown: true });
        await wrapper.setData({ shown: false });
        await wrapper.setData({ shown: true });

        expect(wrapper.find('.child').exists()).toBe(true);
        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
    });

    it('silences a component with several root nodes inside of it without a warning', () => {
        const fragment = {
            name: 'sw-silence-deprecations-test-fragment',
            template: '<div class="first" /><div class="second" />',
            created() {
                Shopware.Feature.triggerDeprecationOrThrow(
                    'V6_9_0_0',
                    'sw-silence-deprecations-test-fragment is deprecated.',
                );
            },
        };

        const wrapper = mountTemplate(
            `
            <sw-silence-deprecations flag="V6_9_0_0" class="legacy">
                <sw-silence-deprecations-test-fragment />
            </sw-silence-deprecations>
        `,
            { 'sw-silence-deprecations-test-fragment': fragment },
        );

        expect(wrapper.findAll('.first, .second')).toHaveLength(2);
        expect(wrapper.find('.legacy').exists()).toBe(false);
        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-fragment is deprecated.');
    });

    it('silences the deprecation plugin for components and props inside of it', () => {
        const legacy = { name: 'sw-silence-deprecations-test-legacy', template: '<div />', deprecated: 'v6.9.0.0' };
        const image = {
            name: 'sw-silence-deprecations-test-image',
            template: '<div />',
            props: { emptyIcon: { type: String, deprecated: 'v6.9.0.0' } },
        };

        mountTemplate(
            `
            <sw-silence-deprecations flag="V6_9_0_0">
                <sw-silence-deprecations-test-legacy />
                <sw-silence-deprecations-test-image empty-icon="regular-image" />
            </sw-silence-deprecations>
        `,
            { 'sw-silence-deprecations-test-legacy': legacy, 'sw-silence-deprecations-test-image': image },
        );

        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-legacy');
        expect(deprecations).not.toHaveTriggered('emptyIcon');
    });

    it('silences what the templates of the components inside of it render', () => {
        const parent = {
            name: 'sw-silence-deprecations-test-parent',
            components,
            template: '<div><sw-silence-deprecations-test-other /></div>',
        };

        mountTemplate(
            `
            <sw-silence-deprecations flag="V6_9_0_0">
                <sw-silence-deprecations-test-parent />
            </sw-silence-deprecations>
        `,
            { 'sw-silence-deprecations-test-parent': parent },
        );

        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-other is deprecated.');
    });

    it('keeps the deprecations of the slot content a wrapper component passes through', () => {
        mountTemplate(
            `
            <sw-silence-deprecations-test-wrapper>
                <sw-silence-deprecations-test-other />
            </sw-silence-deprecations-test-wrapper>
        `,
            { 'sw-silence-deprecations-test-wrapper': wrapperComponent() },
        );

        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
        expect(deprecations).toHaveTriggered('sw-silence-deprecations-test-other is deprecated.');
    });

    it('keeps the deprecations of the slot content it passes into a component inside of it', () => {
        mountTemplate(`
            <sw-silence-deprecations flag="V6_9_0_0">
                <sw-silence-deprecations-test-child>
                    <sw-silence-deprecations-test-other />
                </sw-silence-deprecations-test-child>
            </sw-silence-deprecations>
        `);

        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
        expect(deprecations).toHaveTriggered('sw-silence-deprecations-test-other is deprecated.');
    });

    it('keeps the deprecations of a page a router view in its slot content renders', () => {
        const routerView = {
            name: 'sw-silence-deprecations-test-router-view',
            setup(_props, { slots }) {
                return () => slots.default({ Component: otherDeprecatedChild });
            },
        };

        mountTemplate(
            `
            <sw-silence-deprecations flag="V6_9_0_0">
                <sw-silence-deprecations-test-child>
                    <sw-silence-deprecations-test-router-view v-slot="{ Component }">
                        <component :is="Component" />
                    </sw-silence-deprecations-test-router-view>
                </sw-silence-deprecations-test-child>
            </sw-silence-deprecations>
        `,
            { 'sw-silence-deprecations-test-router-view': routerView },
        );

        expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
        expect(deprecations).toHaveTriggered('sw-silence-deprecations-test-other is deprecated.');
    });

    it('keeps the deprecations of a component nested in another component inside of it', () => {
        const card = { name: 'sw-silence-deprecations-test-card', template: '<div><slot /></div>' };

        mountTemplate(
            `
            <sw-silence-deprecations flag="V6_9_0_0">
                <sw-silence-deprecations-test-card>
                    <sw-silence-deprecations-test-child />
                </sw-silence-deprecations-test-card>
            </sw-silence-deprecations>
        `,
            { 'sw-silence-deprecations-test-card': card },
        );

        expect(deprecations).toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
    });

    it('silences the lifecycle hooks of the components inside of it', () => {
        const mounting = {
            name: 'sw-silence-deprecations-test-mounting',
            template: '<div />',
            mounted() {
                Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'A mounted hook called a deprecated API.');
            },
        };

        mountTemplate(
            `
            <sw-silence-deprecations flag="V6_9_0_0">
                <sw-silence-deprecations-test-mounting />
            </sw-silence-deprecations>
        `,
            { 'sw-silence-deprecations-test-mounting': mounting },
        );

        expect(deprecations).not.toHaveTriggered('A mounted hook called a deprecated API.');
    });

    describe('block overrides', () => {
        it('silences a component an override puts into a block directly inside of it', async () => {
            const page = await buildWithOverride(
                'sw-silence-deprecations-test-override-direct',
                `<sw-silence-deprecations flag="V6_9_0_0">
                    {% block legacy_content %}<sw-silence-deprecations-test-child />{% endblock %}
                </sw-silence-deprecations>`,
                '{% block legacy_content %}{% parent %}<sw-silence-deprecations-test-other />{% endblock %}',
            );

            const wrapper = mount(page);

            expect(wrapper.find('.child').exists()).toBe(true);
            expect(wrapper.find('.other').exists()).toBe(true);
            expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
            expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-other is deprecated.');
        });

        it('keeps the deprecations of a component an override puts into the slot content of a component inside of it', async () => {
            const card = { name: 'sw-silence-deprecations-test-card', template: '<div class="card"><slot /></div>' };
            const page = await buildWithOverride(
                'sw-silence-deprecations-test-override-slot',
                `<sw-silence-deprecations flag="V6_9_0_0">
                    <sw-silence-deprecations-test-card>
                        {% block legacy_card_content %}<span />{% endblock %}
                    </sw-silence-deprecations-test-card>
                </sw-silence-deprecations>`,
                '{% block legacy_card_content %}<sw-silence-deprecations-test-other />{% endblock %}',
                { 'sw-silence-deprecations-test-card': card },
            );

            const wrapper = mount(page);

            expect(wrapper.find('.card .other').exists()).toBe(true);
            expect(deprecations).toHaveTriggered('sw-silence-deprecations-test-other is deprecated.');
        });

        it('silences a component an override puts into the own template of a component inside of it', async () => {
            const panel = await buildWithOverride(
                'sw-silence-deprecations-test-override-panel',
                '<div class="panel">{% block panel_content %}<span />{% endblock %}</div>',
                '{% block panel_content %}<sw-silence-deprecations-test-other />{% endblock %}',
            );

            const wrapper = mountTemplate(
                `
                <sw-silence-deprecations flag="V6_9_0_0">
                    <sw-silence-deprecations-test-panel />
                </sw-silence-deprecations>
            `,
                { 'sw-silence-deprecations-test-panel': panel },
            );

            expect(wrapper.find('.panel .other').exists()).toBe(true);
            expect(deprecations).not.toHaveTriggered('sw-silence-deprecations-test-other is deprecated.');
        });

        it('keeps the deprecations of a component an override moves out of it', async () => {
            const page = await buildWithOverride(
                'sw-silence-deprecations-test-override-replace',
                `<div>
                    {% block legacy %}
                        <sw-silence-deprecations flag="V6_9_0_0"><sw-silence-deprecations-test-child /></sw-silence-deprecations>
                    {% endblock %}
                </div>`,
                '{% block legacy %}<sw-silence-deprecations-test-child />{% endblock %}',
            );

            mount(page);

            expect(deprecations).toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
        });
    });

    it('keeps the deprecations of components outside of it', () => {
        mountTemplate(`
            <div>
                <sw-silence-deprecations flag="V6_9_0_0">
                    <sw-silence-deprecations-test-child />
                </sw-silence-deprecations>
                <sw-silence-deprecations-test-child />
            </div>
        `);

        expect(deprecations).toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
    });

    it('reports a deprecation outside of it after the same one was silenced', () => {
        useProductionDeprecationGuard();
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const ordered = {
            name: 'sw-silence-deprecations-test-ordered',
            template: '<div />',
            created() {
                Shopware.Feature.triggerDeprecationOrThrow(
                    'V6_9_0_0',
                    'sw-silence-deprecations-test-ordered is deprecated.',
                );
            },
        };

        mountTemplate(
            `
            <div>
                <sw-silence-deprecations flag="V6_9_0_0">
                    <sw-silence-deprecations-test-ordered />
                </sw-silence-deprecations>
                <sw-silence-deprecations-test-ordered />
            </div>
        `,
            { 'sw-silence-deprecations-test-ordered': ordered },
        );

        expect(warn).toHaveBeenCalledWith('[Deprecation]', 'sw-silence-deprecations-test-ordered is deprecated.');
    });

    it('keeps the deprecations of other majors', () => {
        mountTemplate(`
            <sw-silence-deprecations flag="V6_8_0_0">
                <sw-silence-deprecations-test-child />
            </sw-silence-deprecations>
        `);

        expect(deprecations).toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
    });

    it('keeps the deprecations of event handlers and watchers', async () => {
        const interactive = {
            name: 'sw-silence-deprecations-test-interactive',
            template: '<button @click="onClick" />',
            data: () => ({ count: 0 }),
            watch: {
                count() {
                    Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'A watcher called a deprecated API.');
                },
            },
            methods: {
                onClick() {
                    Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'A click handler called a deprecated API.');
                    this.count += 1;
                },
            },
        };

        const wrapper = mountTemplate(
            `
            <sw-silence-deprecations flag="V6_9_0_0">
                <sw-silence-deprecations-test-interactive />
            </sw-silence-deprecations>
        `,
            { 'sw-silence-deprecations-test-interactive': interactive },
        );
        await wrapper.find('button').trigger('click');

        expect(deprecations).toHaveTriggered('A click handler called a deprecated API.');
        expect(deprecations).toHaveTriggered('A watcher called a deprecated API.');
    });

    it('keeps the deprecations of code after an await', async () => {
        const loading = {
            name: 'sw-silence-deprecations-test-loading',
            template: '<div />',
            async created() {
                await Promise.resolve();
                Shopware.Feature.triggerDeprecationOrThrow('V6_9_0_0', 'Code after an await called a deprecated API.');
            },
        };

        mountTemplate(
            `
            <sw-silence-deprecations flag="V6_9_0_0">
                <sw-silence-deprecations-test-loading />
            </sw-silence-deprecations>
        `,
            { 'sw-silence-deprecations-test-loading': loading },
        );
        await flushPromises();

        expect(deprecations).toHaveTriggered('Code after an await called a deprecated API.');
    });

    it('silences nothing and logs an error for an unknown flag', () => {
        const error = jest.spyOn(console, 'error').mockImplementation(() => {});

        mountTemplate(`
            <sw-silence-deprecations flag="V6_9_0">
                <sw-silence-deprecations-test-child />
            </sw-silence-deprecations>
        `);

        expect(error).toHaveBeenCalledWith(
            '[Feature]',
            '<sw-silence-deprecations flag="V6_9_0"> silences nothing: "V6_9_0" is an unknown feature flag. Please fix it.',
        );
        expect(deprecations).toHaveTriggered('sw-silence-deprecations-test-child is deprecated.');
    });

    it.activeFeatureFlags(['v6.9.0.0'])('silences nothing once the major is active', () => {
        expect(() =>
            mountTemplate(`
                <sw-silence-deprecations flag="V6_9_0_0">
                    <sw-silence-deprecations-test-child />
                </sw-silence-deprecations>
            `),
        ).toThrow('Tried to access deprecated functionality: sw-silence-deprecations-test-child is deprecated.');
    });
});
