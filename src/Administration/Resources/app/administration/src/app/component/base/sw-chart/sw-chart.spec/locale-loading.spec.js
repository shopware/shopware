/**
 * @sw-package framework
 */

import nl from 'apexcharts/dist/locales/nl.json';
import localeLoaders from '../locales';
import { apexchartStub, setup } from './sw-chart.fixtures';

describe('asyncComponent/base/sw-chart - locale loading', () => {
    const setAdminLocale = (locale) => {
        Shopware.Store.get('session').setAdminLocaleState({
            locale,
            locales: ['en-GB', 'nl-NL', locale],
        });
    };

    const chartOptions = (wrapper) => wrapper.getComponent(apexchartStub).props('options').chart;

    const deferred = () => {
        let resolve;
        const promise = new Promise((done) => {
            resolve = done;
        });

        return { promise, resolve };
    };

    let warnSpy;

    beforeEach(() => {
        warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        Shopware.Component.getOverrideRegistry().delete('sw-chart');
        jest.restoreAllMocks();
    });

    it.each(Object.entries(localeLoaders))('provides the ApexCharts locale named "%s"', async (language, load) => {
        const { default: locale } = await load();

        expect(locale.name).toBe(language);
    });

    it.each([
        [
            'nl-NL',
            [],
            ['nl'],
            nl,
        ],
        [
            'nl-NL',
            ['nl'],
            ['nl'],
            null,
        ],
        [
            'en-GB',
            [],
            [],
            null,
        ],
        [
            'foo-BAR',
            [],
            [],
            null,
        ],
    ])(
        'renders the chart for admin locale %s when %j fail to load',
        async (adminLocale, failingLocales, expectedRequests, expectedLocale) => {
            setAdminLocale(adminLocale);
            const requested = [];
            Object.keys(localeLoaders).forEach((locale) => {
                const load = localeLoaders[locale];
                jest.spyOn(localeLoaders, locale).mockImplementation(() => {
                    requested.push(locale);
                    return failingLocales.includes(locale) ? Promise.reject(new Error('offline')) : load();
                });
            });

            const wrapper = await setup();
            await flushPromises();

            expect(requested).toEqual(expectedRequests);
            expect(warnSpy).toHaveBeenCalledTimes(failingLocales.length);
            expect(chartOptions(wrapper).defaultLocale).toBe(expectedLocale?.name ?? 'en');
            expect(chartOptions(wrapper).locales).toEqual(expectedLocale ? [expectedLocale] : undefined);
        },
    );

    it('renders the chart only after the selected locale has loaded', async () => {
        setAdminLocale('nl-NL');
        const nlLocale = deferred();
        jest.spyOn(localeLoaders, 'nl').mockReturnValue(nlLocale.promise);

        const wrapper = await setup();
        await flushPromises();

        expect(wrapper.findComponent(apexchartStub).exists()).toBe(false);

        nlLocale.resolve({ default: nl });
        await flushPromises();

        expect(chartOptions(wrapper).defaultLocale).toBe('nl');
    });

    it('lets an async override wait for the base locale loader', async () => {
        setAdminLocale('nl-NL');
        const nlLocale = deferred();
        jest.spyOn(localeLoaders, 'nl').mockReturnValue(nlLocale.promise);
        const localeSeenByOverride = jest.fn();
        Shopware.Component.override('sw-chart', {
            methods: {
                async loadLocaleConfig() {
                    await this.$super('loadLocaleConfig');
                    localeSeenByOverride(this.localeConfig?.name);
                },
            },
        });

        const wrapper = await setup();
        await flushPromises();

        expect(localeSeenByOverride).not.toHaveBeenCalled();

        nlLocale.resolve({ default: nl });
        await flushPromises();

        expect(localeSeenByOverride).toHaveBeenCalledWith('nl');
        expect(chartOptions(wrapper).defaultLocale).toBe('nl');
    });

    it('renders in English first when a sync override does not await the base locale loader', async () => {
        setAdminLocale('nl-NL');
        const nlLocale = deferred();
        jest.spyOn(localeLoaders, 'nl').mockReturnValue(nlLocale.promise);
        Shopware.Component.override('sw-chart', {
            methods: {
                loadLocaleConfig() {
                    this.$super('loadLocaleConfig');
                },
            },
        });

        const wrapper = await setup();
        await flushPromises();

        expect(chartOptions(wrapper).defaultLocale).toBe('en');

        nlLocale.resolve({ default: nl });
        await flushPromises();

        expect(chartOptions(wrapper).defaultLocale).toBe('nl');
    });

    it('reports a failing locale override to the Vue error handler', async () => {
        const errorHandler = jest.fn();
        Shopware.Component.override('sw-chart', {
            methods: {
                async loadLocaleConfig() {
                    throw new Error('broken override');
                },
            },
        });

        await setup({ config: { errorHandler } });
        await flushPromises();

        expect(errorHandler).toHaveBeenCalledWith(
            expect.objectContaining({ message: 'broken override' }),
            expect.anything(),
            expect.anything(),
        );
    });
});
