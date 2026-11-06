/**
 * @sw-package framework
 */
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import useI18n from './use-i18n';

/** An i18n instance with the options `vue.adapter.ts` creates the Administration's one with. */
function createAdminI18n() {
    return createI18n({
        legacy: false,
        allowComposition: true,
        locale: 'de-DE',
        fallbackLocale: 'en-GB',
        fallbackWarn: false,
        silentFallbackWarn: true,
        messages: {
            'en-GB': { probe: { onlyInFallback: 'Margin: {n} %' } },
            'de-DE': { probe: { translated: 'Marge: {n} %' } },
        },
    });
}

/** What an extension component's argument-less `useI18n()` call is typed as. */
type ExtensionComposer = ReturnType<typeof callWithoutOptions>;

function callWithoutOptions() {
    return useI18n();
}

/** Calls `useI18n()` the way a component of an extension does, inside setup of a mounted app. */
function useI18nInComponent(i18n: ReturnType<typeof createAdminI18n>): ExtensionComposer {
    let composer: ExtensionComposer | undefined;

    createApp(
        defineComponent({
            setup() {
                composer = callWithoutOptions();

                return () => h('div');
            },
        }),
    )
        .use(i18n)
        .mount(document.createElement('div'));

    if (!composer) {
        throw new Error('setup() did not run');
    }

    return composer;
}

describe('src/app/composables/use-i18n', () => {
    it('returns the global composer when called without options', () => {
        const i18n = createAdminI18n();

        expect(useI18nInComponent(i18n)).toBe(i18n.global);
    });

    it('interpolates named params in the active locale', () => {
        const { t } = useI18nInComponent(createAdminI18n());

        expect(t('probe.translated', { n: 42 })).toBe('Marge: 42 %');
    });

    it('falls back to the fallback locale for a key the active locale lacks', () => {
        const { t } = useI18nInComponent(createAdminI18n());

        expect(t('probe.onlyInFallback', { n: 42 })).toBe('Margin: 42 %');
    });
});
