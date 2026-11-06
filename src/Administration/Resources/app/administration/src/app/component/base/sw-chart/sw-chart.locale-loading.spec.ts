/**
 * @sw-package framework
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import en from 'apexcharts/dist/locales/en.json';
import nl from 'apexcharts/dist/locales/nl.json';
import localeLoaders from './locales';

jest.mock('./locales', () => ({
    __esModule: true,
    default: { en: jest.fn(), nl: jest.fn() },
}));

const chart = {
    name: 'apexchart',
    props: ['options', 'type'],
    template:
        '<output :data-locale="options.chart.defaultLocale" :data-month="options.chart.locales?.[0]?.options.months[0]" :data-locale-count="options.chart.locales?.length ?? 0" :data-has-locales="Object.hasOwn(options.chart, `locales`)" />',
};

describe('chart locale loading', () => {
    let wrapper: VueWrapper | undefined;
    const loaders = { en: jest.mocked(localeLoaders.en), nl: jest.mocked(localeLoaders.nl) };

    beforeEach(() => {
        jest.clearAllMocks();
        loaders.en.mockResolvedValue({ ...en, default: en });
        Shopware.Store.get('session').setAdminLocaleState({
            locale: 'nl-NL',
            languageId: Shopware.Defaults.systemLanguageId,
            locales: ['en-GB', 'nl-NL'],
        });
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
        Shopware.Component.getOverrideRegistry().delete('sw-chart');
        jest.restoreAllMocks();
    });

    it('loads only the selected locale and waits before rendering the chart', async () => {
        let finish: (value: Awaited<ReturnType<typeof localeLoaders.nl>>) => void = () => {};
        loaders.nl.mockImplementation(
            () =>
                new Promise((resolve) => {
                    finish = resolve;
                }),
        );
        wrapper = mount(await wrapTestComponent('sw-chart', { sync: true }), {
            props: { type: 'line', series: [], options: {} },
            global: { stubs: { apexchart: chart } },
        });
        expect(loaders.nl).toHaveBeenCalledTimes(1);
        expect(loaders.en).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-chart').exists()).toBe(false);

        finish({ ...nl, default: nl });
        await flushPromises();
        expect(wrapper.find('.sw-chart').exists()).toBe(true);
        expect(wrapper.get('output').attributes('data-locale')).toBe('nl');
        expect(wrapper.get('output').attributes('data-month')).toBe(nl.options.months[0]);
    });

    it('uses English chart options when loading the selected locale fails', async () => {
        loaders.nl.mockRejectedValue(new Error('Locale unavailable'));
        wrapper = mount(await wrapTestComponent('sw-chart', { sync: true }), {
            props: { type: 'line', series: [], options: {} },
            global: { stubs: { apexchart: chart } },
        });
        await flushPromises();
        expect(loaders.en).toHaveBeenCalledTimes(1);
        expect(wrapper.get('output').attributes('data-locale')).toBe('en');
        expect(wrapper.get('output').attributes('data-month')).toBe(en.options.months[0]);
        expect(wrapper.find('.sw-chart').exists()).toBe(true);
    });
    it('does not request English twice if its download fails', async () => {
        Shopware.Store.get('session').setAdminLocaleState({
            locale: 'en-GB',
            languageId: Shopware.Defaults.systemLanguageId,
            locales: ['en-GB'],
        });
        loaders.en.mockRejectedValue(new Error('English unavailable'));
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        wrapper = mount(await wrapTestComponent('sw-chart', { sync: true }), {
            props: { type: 'line', series: [], options: {} },
            global: { stubs: { apexchart: chart } },
        });
        await flushPromises();
        expect(loaders.en).toHaveBeenCalledTimes(1);
        expect(loaders.nl).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-chart').exists()).toBe(true);
        expect(wrapper.get('output').attributes('data-locale')).toBe('en');
        expect(wrapper.get('output').attributes('data-locale-count')).toBe('0');
        expect(wrapper.get('output').attributes('data-has-locales')).toBe('false');
    });

    it('renders with built-in English if both locale downloads fail', async () => {
        loaders.nl.mockRejectedValue(new Error('Dutch unavailable'));
        loaders.en.mockRejectedValue(new Error('English unavailable'));
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        wrapper = mount(await wrapTestComponent('sw-chart', { sync: true }), {
            props: { type: 'line', series: [], options: {} },
            global: { stubs: { apexchart: chart } },
        });
        await flushPromises();
        expect(loaders.nl).toHaveBeenCalledTimes(1);
        expect(loaders.en).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-chart').exists()).toBe(true);
        expect(wrapper.get('output').attributes('data-locale')).toBe('en');
        expect(wrapper.get('output').attributes('data-locale-count')).toBe('0');
        expect(wrapper.get('output').attributes('data-has-locales')).toBe('false');
    });

    it('loads English directly for an unsupported Administration language', async () => {
        Shopware.Store.get('session').setAdminLocaleState({
            locale: 'foo-BAR',
            languageId: Shopware.Defaults.systemLanguageId,
            locales: ['foo-BAR'],
        });
        wrapper = mount(await wrapTestComponent('sw-chart', { sync: true }), {
            props: { type: 'line', series: [], options: {} },
            global: { stubs: { apexchart: chart } },
        });
        await flushPromises();
        expect(loaders.en).toHaveBeenCalledTimes(1);
        expect(loaders.nl).not.toHaveBeenCalled();
        expect(wrapper.get('output').attributes('data-locale')).toBe('en');
    });
    it('allows an async override to wait for the base locale loader', async () => {
        const afterBaseLocale = jest.fn();
        const afterBaseCreated = jest.fn();
        let finish: (value: Awaited<ReturnType<typeof localeLoaders.nl>>) => void = () => {};
        loaders.nl.mockImplementation(
            () =>
                new Promise((resolve) => {
                    finish = resolve;
                }),
        );
        Shopware.Component.override('sw-chart', () =>
            Promise.resolve({
                methods: {
                    async createdComponent(this: { $super: (name: string) => Promise<void> }) {
                        await this.$super('createdComponent');
                        afterBaseCreated();
                    },
                    async loadLocaleConfig(this: { $super: (name: string) => Promise<void> }) {
                        await this.$super('loadLocaleConfig');
                        afterBaseLocale();
                    },
                },
            }),
        );
        wrapper = mount(await wrapTestComponent('sw-chart', { sync: true }), {
            props: { type: 'line', series: [], options: {} },
            global: { stubs: { apexchart: chart } },
        });
        expect(afterBaseLocale).not.toHaveBeenCalled();
        expect(afterBaseCreated).not.toHaveBeenCalled();
        expect(wrapper.find('output').exists()).toBe(false);
        finish({ ...nl, default: nl });
        await flushPromises();
        expect(afterBaseLocale).toHaveBeenCalledTimes(1);
        expect(afterBaseCreated).toHaveBeenCalledTimes(1);
        expect(wrapper.get('output').attributes('data-locale')).toBe('nl');
    });

    it('still accepts a synchronous override that supplies its own locale configuration', async () => {
        Shopware.Component.override('sw-chart', () =>
            Promise.resolve({
                methods: {
                    loadLocaleConfig(this: { localeConfig: typeof en | null }) {
                        this.localeConfig = en;
                    },
                },
            }),
        );
        wrapper = mount(await wrapTestComponent('sw-chart', { sync: true }), {
            props: { type: 'line', series: [], options: {} },
            global: { stubs: { apexchart: chart } },
        });
        await flushPromises();
        expect(loaders.nl).not.toHaveBeenCalled();
        expect(loaders.en).not.toHaveBeenCalled();
        expect(wrapper.get('output').attributes('data-locale')).toBe('en');
    });
});
