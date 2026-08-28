/**
 * @sw-package framework
 */
import 'src/app/mixin/translate-with-fallback.mixin';
import { mount } from '@vue/test-utils';

const messages = {
    'en-GB': { 'sw-grid.column.name': 'Name (EN)' },
    'de-DE': { 'sw-grid.column.name': 'Name (DE)' },
};

async function createWrapper({ locale, $te, $t } = {}) {
    return mount(
        {
            template: '<div class="sw-mock"></div>',
            mixins: [
                Shopware.Mixin.getByName('translate-with-fallback'),
            ],
        },
        {
            global: {
                mocks: {
                    $te: $te ?? ((key, l) => Boolean(messages[l ?? locale]?.[key])),
                    $t: $t ?? ((key, l) => messages[l ?? locale]?.[key] ?? key),
                },
            },
        },
    );
}

describe('src/app/mixin/translate-with-fallback.mixin.ts', () => {
    beforeEach(() => {
        Shopware.Context.app.fallbackLocale = 'en-GB';
    });

    it('returns the translated value when the snippet exists in the current locale', async () => {
        const wrapper = await createWrapper({ locale: 'de-DE' });

        expect(wrapper.vm.tWithFallback('sw-grid.column.name')).toBe('Name (DE)');
    });

    it('falls back to the fallback locale when the snippet is missing in the current locale', async () => {
        const wrapper = await createWrapper({ locale: 'fr-FR' });

        expect(wrapper.vm.tWithFallback('sw-grid.column.name')).toBe('Name (EN)');
    });

    it('returns the raw key when neither current nor fallback locale has the snippet', async () => {
        const wrapper = await createWrapper({ locale: 'fr-FR' });

        expect(wrapper.vm.tWithFallback('Plain Text Label')).toBe('Plain Text Label');
    });

    it('returns an empty string for an empty input', async () => {
        const wrapper = await createWrapper({ locale: 'en-GB' });

        expect(wrapper.vm.tWithFallback('')).toBe('');
        expect(wrapper.vm.tWithFallback(undefined)).toBe('');
    });

    it('does not consult the fallback locale when no fallbackLocale is configured', async () => {
        const $te = jest.fn(() => false);
        const $t = jest.fn((key) => key);
        const wrapper = await createWrapper({ $te, $t });

        Shopware.Context.app.fallbackLocale = '';
        $te.mockClear();

        expect(wrapper.vm.tWithFallback('sw-grid.column.name')).toBe('sw-grid.column.name');
        expect($te).toHaveBeenCalledTimes(1);
        expect($te).toHaveBeenCalledWith('sw-grid.column.name');
    });
});
