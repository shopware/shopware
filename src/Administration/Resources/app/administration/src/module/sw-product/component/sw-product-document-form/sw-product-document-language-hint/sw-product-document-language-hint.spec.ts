/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';

describe('sw-product-document-language-hint', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    let wrapper: VueWrapper;
    const getLanguage = jest.fn();

    beforeEach(() => {
        getLanguage.mockReset().mockResolvedValue({ name: 'English' });
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
    });

    afterEach(() => {
        wrapper?.unmount();
        Shopware.Context.api.languageId = originalLanguageId;
    });

    async function createWrapper() {
        wrapper = shallowMount(await wrapTestComponent('sw-product-document-language-hint', { sync: true }), {
            global: {
                provide: { repositoryFactory: { create: () => ({ get: getLanguage }) } },
                stubs: { 'i18n-t': { template: '<span><slot name="language" /></span>' } },
            },
        });
        await flushPromises();
        return wrapper;
    }

    it('loads the default language name and switches to its ID when clicked', async () => {
        await createWrapper();
        expect(getLanguage).toHaveBeenCalledWith(Shopware.Context.api.systemLanguageId);
        expect(wrapper.text()).toContain('English');
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('change-language')).toEqual([[Shopware.Context.api.systemLanguageId]]);
    });

    it('does not load a language name while editing the default language', async () => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        await createWrapper();
        expect(getLanguage).not.toHaveBeenCalled();
    });

    it('keeps the language switch available when the name cannot be loaded', async () => {
        getLanguage.mockRejectedValueOnce(new Error('Language unavailable'));
        await createWrapper();
        expect(wrapper.text()).toContain('sw-product.documentForm.defaultLanguage');
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('change-language')).toEqual([[Shopware.Context.api.systemLanguageId]]);
    });
});
