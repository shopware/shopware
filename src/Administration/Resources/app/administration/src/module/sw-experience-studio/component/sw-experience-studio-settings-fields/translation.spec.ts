import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { nextTick } from 'vue';
import en from '../../snippet/en.json';
import de from '../../snippet/de.json';
import component from './index';

describe('Experience Studio settings translations', () => {
    it('uses the fallback locale for the default panel title', () => {
        const previousFallbackLocale = Shopware.Context.app.fallbackLocale;
        Shopware.Context.app.fallbackLocale = 'en-GB';
        const i18n = createI18n({
            legacy: false,
            locale: 'fr-FR',
            fallbackLocale: 'en-GB',
            fallbackWarn: false,
            messages: { 'fr-FR': {}, 'en-GB': en },
        });
        const methods = (component as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

        try {
            expect(
                methods.getPanelTitle.call(
                    {
                        $te: (key: string, locale?: 'en-GB' | 'fr-FR') => i18n.global.te(key, locale),
                        $t: (key: string) => i18n.global.t(key),
                        getPanelSnippetKey: () => 'sw-experience-studio.detail.elementSettings.panelGeneral',
                    },
                    { technicalName: null },
                ),
            ).toBe(en['sw-experience-studio'].detail.elementSettings.panelGeneral);
        } finally {
            Shopware.Context.app.fallbackLocale = previousFallbackLocale;
        }
    });

    it('translates select labels without changing their values', async () => {
        const i18n = createI18n({ legacy: false, locale: 'de-DE', messages: { 'de-DE': de, 'en-GB': en } });
        const wrapper = mount(component, {
            props: {
                fields: [
                    {
                        key: 'displayMode',
                        property: {
                            type: 'string',
                            contextTypes: ['single'],
                            translatable: false,
                            default: 'auto',
                            required: false,
                            mappable: false,
                            inlineMappable: false,
                            defaultMapping: null,
                            description: 'How the gallery images are sized.',
                            title: 'Display Mode',
                            titleSnippet: 'sw-experience-studio.elements.media-gallery.displayMode.title',
                            enum: ['auto', 'fixedHeight'],
                            adminUI: {
                                component: 'select',
                                props: {
                                    options: [
                                        {
                                            value: 'auto',
                                            label: 'Automatic',
                                            labelSnippet:
                                                'sw-experience-studio.elements.media-gallery.displayMode.options.auto.label',
                                        },
                                        {
                                            value: 'fixedHeight',
                                            label: 'Fixed height',
                                            labelSnippet:
                                                'sw-experience-studio.elements.media-gallery.displayMode.options.fixedHeight.label',
                                        },
                                    ],
                                },
                            },
                        },
                    },
                ],
                values: { displayMode: 'fixedHeight' },
            },
            global: {
                plugins: [i18n],
                mocks: { $t: (key: string) => i18n.global.t(key), $te: (key: string) => i18n.global.te(key) },
                provide: { repositoryFactory: {} },
                stubs: {
                    'sw-collapse': { template: '<div><slot name="content" /></div>' },
                    'mt-select': {
                        props: ['options', 'modelValue'],
                        template:
                            '<div><span v-for="option in options" :key="option.value">{{ option.label }}</span><b>{{ modelValue }}</b></div>',
                    },
                },
            },
        });

        try {
            expect(wrapper.text()).toContain('Automatisch');
            expect(wrapper.text()).toContain('Feste Höhe');
            expect(wrapper.text()).toContain('fixedHeight');
            i18n.global.locale.value = 'en-GB';
            await nextTick();
            expect(wrapper.text()).toContain('Automatic');
            expect(wrapper.text()).toContain('Fixed height');
            expect(wrapper.text()).toContain('fixedHeight');
        } finally {
            wrapper.unmount();
        }
    });

    it('renders a translated YAML field and reacts to an Administration language change', async () => {
        const i18n = createI18n({ legacy: false, locale: 'de-DE', messages: { 'de-DE': de, 'en-GB': en } });
        const wrapper = mount(component, {
            props: {
                fields: [
                    {
                        key: 'height',
                        property: {
                            type: 'string',
                            contextTypes: ['single'],
                            translatable: false,
                            enum: null,
                            required: false,
                            mappable: false,
                            inlineMappable: false,
                            defaultMapping: null,
                            description: 'Image height attribute.',
                            adminUI: null,
                            title: 'Height',
                            titleSnippet: 'sw-experience-studio.elements.media-image.height.title',
                            default: 'auto',
                        },
                    },
                ],
                values: {},
            },
            global: {
                plugins: [i18n],
                mocks: { $t: (key: string) => i18n.global.t(key), $te: (key: string) => i18n.global.te(key) },
                provide: { repositoryFactory: {} },
                stubs: {
                    'sw-collapse': { template: '<div><slot name="content" /></div>' },
                    'mt-text-field': { props: ['label'], template: '<span>{{ label }}</span>' },
                },
            },
        });

        try {
            expect(wrapper.text()).toContain('Höhe');
            i18n.global.locale.value = 'en-GB';
            await nextTick();
            expect(wrapper.text()).toContain('Height');
        } finally {
            wrapper.unmount();
        }
    });
});
