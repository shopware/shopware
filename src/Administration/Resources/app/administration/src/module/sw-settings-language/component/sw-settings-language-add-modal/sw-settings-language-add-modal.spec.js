/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(existingLanguages = []) {
    const install = jest.fn().mockResolvedValue(undefined);
    const languageSearch = jest.fn().mockResolvedValue(existingLanguages.map((code) => ({ locale: { code } })));

    const getList = jest.fn().mockResolvedValue({
        total: 2,
        items: [
            { locale: 'fr-FR', name: 'Français', lastUpdate: null, progress: 90, isPseudoLanguage: false },
            {
                locale: 'it-IT',
                name: 'Italiano',
                lastUpdate: '2026-07-06T22:29:10+00:00',
                progress: 50,
                isPseudoLanguage: false,
            },
        ],
    });

    const getMeta = jest.fn().mockResolvedValue({
        builtInLocales: [
            'de-DE',
            'en-GB',
        ],
        communityTranslationsUrl: 'https://translate.shopware.com',
        documentationUrlSnippetKey: 'sw-settings-language.addModal.docsUrl',
        completenessThreshold: 90,
    });

    const wrapper = mount(await wrapTestComponent('sw-settings-language-add-modal', { sync: true }), {
        global: {
            provide: {
                translationService: { getList, getMeta, install },
                repositoryFactory: {
                    create: () => ({ search: languageSearch }),
                },
            },
            mocks: {
                $t: (key, values) => values?.link ?? key,
            },
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-single-select': true,
                'sw-help-text': true,
                'mt-link': true,
                'mt-button': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, install, getList };
}

describe('module/sw-settings-language/component/sw-settings-language-add-modal', () => {
    it('loads the available languages and disables already installed ones', async () => {
        const { wrapper, getList } = await createWrapper();

        expect(getList).toHaveBeenCalled();
        expect(wrapper.vm.languageOptions).toEqual([
            { value: 'fr-FR', label: 'Français', disabled: false, isPseudoLanguage: false },
            { value: 'it-IT', label: 'Italiano', disabled: true, isPseudoLanguage: false },
        ]);
    });

    it('disables an existing but unlinked language', async () => {
        const { wrapper } = await createWrapper(['fr-FR']);

        const frOption = wrapper.vm.languageOptions.find((option) => option.value === 'fr-FR');

        expect(frOption.disabled).toBe(true);
    });

    it('disables the language select while loading', async () => {
        const { wrapper } = await createWrapper();
        const select = wrapper.find('.sw-settings-language-add-modal__language-select');

        expect(select.attributes('disabled')).toBeUndefined();

        wrapper.vm.isLoading = true;
        await wrapper.vm.$nextTick();

        expect(select.attributes('disabled')).toBe('true');
    });

    it('pins Acholi to the bottom regardless of its alphabetical position', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.translations = [
            { locale: 'ach-UG', name: 'Acholi', lastUpdate: null, isPseudoLanguage: true },
            { locale: 'fr-FR', name: 'Français', lastUpdate: null, isPseudoLanguage: false },
            { locale: 'zu-ZA', name: 'Zulu', lastUpdate: null, isPseudoLanguage: false },
        ];

        expect(wrapper.vm.languageOptions.map((option) => option.value)).toEqual([
            'fr-FR',
            'zu-ZA',
            'ach-UG',
        ]);
    });

    it('installs the selected language and emits language-added', async () => {
        const { wrapper, install } = await createWrapper();

        wrapper.vm.selectedLocale = 'fr-FR';
        await wrapper.vm.onAddLanguage();

        expect(install).toHaveBeenCalledWith({ locales: ['fr-FR'], activate: true });
        expect(wrapper.emitted('language-added')).toHaveLength(1);
        expect(wrapper.emitted('language-added')[0]).toEqual(['fr-FR']);
    });

    it('does not install when no language is selected', async () => {
        const { wrapper, install } = await createWrapper();

        await wrapper.vm.onAddLanguage();

        expect(install).not.toHaveBeenCalled();
        expect(wrapper.emitted('language-added')).toBeUndefined();
    });

    it('does not emit language-added when the install fails', async () => {
        const { wrapper, install } = await createWrapper();
        install.mockRejectedValueOnce(new Error('failed'));

        wrapper.vm.selectedLocale = 'fr-FR';
        await wrapper.vm.onAddLanguage();

        expect(wrapper.emitted('language-added')).toBeUndefined();
    });

    it('marks translations as available when completeness is at or above the threshold', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.selectedLocale = 'fr-FR';

        expect(wrapper.vm.translationsSufficient).toBe(true);
        expect(wrapper.vm.translationsHintTextKey).toBe('sw-settings-language.addModal.translationsAvailable');
    });

    it('warns about missing translations when completeness is below the threshold', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.selectedLocale = 'it-IT';

        expect(wrapper.vm.translationsSufficient).toBe(false);
        expect(wrapper.vm.translationsHintTextKey).toBe('sw-settings-language.addModal.translationsIncomplete');
    });

    it('reads the documentation url snippet key from the meta response', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.documentationUrlSnippetKey).toBe('sw-settings-language.addModal.docsUrl');
    });

    it('reads the completeness threshold from the translation list response', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.completenessThreshold).toBe(90);
    });

    it('treats a language without progress data as available', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.translations = [{ locale: 'xx-XX', name: 'Test', lastUpdate: null, progress: null }];
        wrapper.vm.selectedLocale = 'xx-XX';

        expect(wrapper.vm.translationsSufficient).toBe(true);
    });

    it('emits close', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.onClose();

        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
