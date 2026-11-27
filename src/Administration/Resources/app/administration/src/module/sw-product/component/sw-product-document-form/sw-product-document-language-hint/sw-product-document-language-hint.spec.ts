/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';

describe('sw-product-document-language-hint', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    let wrapper: VueWrapper;
    let emitEvent: jest.SpyInstance;

    beforeEach(() => {
        emitEvent = jest.spyOn(Shopware.Utils.EventBus, 'emit');
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
        Shopware.Context.api.languageId = originalLanguageId;
    });

    async function createWrapper(getLanguage: jest.Mock) {
        wrapper = shallowMount(await wrapTestComponent('sw-product-document-language-hint', { sync: true }), {
            global: {
                provide: { repositoryFactory: { create: () => ({ get: getLanguage }) } },
                stubs: { 'i18n-t': { template: '<span><slot name="language" /></span>' } },
            },
        });
        await flushPromises();
        return wrapper;
    }

    it('shows the default language name and requests a language switch when its link is clicked', async () => {
        const getLanguage = jest.fn().mockResolvedValue({ name: 'English' });
        await createWrapper(getLanguage);
        expect(getLanguage).toHaveBeenCalledWith(Shopware.Context.api.systemLanguageId);
        expect(wrapper.text()).toContain('English');
        await wrapper.get('button').trigger('click');
        expect(emitEvent).toHaveBeenCalledWith('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
    });

    it('keeps the language switch available when the default language cannot be loaded', async () => {
        await createWrapper(jest.fn().mockRejectedValue(new Error('Unavailable')));
        expect(wrapper.text()).toContain('sw-product.documentForm.defaultLanguage');
        await wrapper.get('button').trigger('click');
        expect(emitEvent).toHaveBeenCalledWith('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
    });
});
