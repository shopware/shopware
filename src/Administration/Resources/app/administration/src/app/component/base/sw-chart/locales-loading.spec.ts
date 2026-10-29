/**
 * @sw-package framework
 */
import loaders from './locales';

describe('chart locale files', () => {
    it.each(Object.entries(loaders))('loads the ApexCharts locale for %s', async (language, load) => {
        const { default: locale } = await load();

        expect(locale.name).toBe(language);
        expect(locale.options.months).toHaveLength(12);
        expect(locale.options.days).toHaveLength(7);
    });
});
