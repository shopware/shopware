/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';

describe('sw-product-document-empty-state', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    let wrapper: VueWrapper;

    async function createWrapper(canAddDocuments = true, get = jest.fn().mockResolvedValue({ name: 'English' })) {
        return shallowMount(await wrapTestComponent('sw-product-document-empty-state', { sync: true }), {
            props: { canAddDocuments },
            global: {
                provide: { repositoryFactory: { create: () => ({ get }) } },
                stubs: {
                    'sw-product-document-language-hint': await wrapTestComponent('sw-product-document-language-hint', {
                        sync: true,
                    }),
                    'i18n-t': { template: '<span><slot name="language"/></span>' },
                },
            },
        });
    }

    beforeEach(() => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
    });

    afterEach(() => {
        wrapper?.unmount();
        Shopware.Context.api.languageId = originalLanguage;
    });

    it('keeps the default-language Add action without loading a language', async () => {
        const get = jest.fn();
        wrapper = await createWrapper(true, get);
        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('add-documents')).toEqual([[]]);
        expect(wrapper.find('.mt-link').exists()).toBe(false);
        expect(get).not.toHaveBeenCalled();
    });

    it('disables adding documents when the product cannot be edited', async () => {
        wrapper = await createWrapper(false);
        expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
    });

    it('offers the configured default language instead of an Add action in another language', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        const get = jest.fn().mockResolvedValue({ name: 'Français' });
        wrapper = await createWrapper(false, get);
        await flushPromises();

        expect(get).toHaveBeenCalledWith(Shopware.Context.api.systemLanguageId);
        expect(wrapper.find('.mt-button').exists()).toBe(false);
        const link = wrapper.get('button.mt-link');
        expect(link.text()).toBe("'Français'");
        await link.trigger('click');
        expect(wrapper.emitted('change-language')).toEqual([[Shopware.Context.api.systemLanguageId]]);
        expect(wrapper.emitted('add-documents')).toBeUndefined();
    });

    it('keeps language switching available if the language name cannot be loaded', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper(false, jest.fn().mockRejectedValue(new Error('Language lookup failed')));
        await flushPromises();

        expect(wrapper.get('button.mt-link').text()).toContain('sw-product.documentForm.defaultLanguage');
        await wrapper.get('button.mt-link').trigger('click');
        expect(wrapper.emitted('change-language')).toEqual([[Shopware.Context.api.systemLanguageId]]);
    });

    it('offers Add documents after switching back to the default language', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        await flushPromises();
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        await flushPromises();

        expect(wrapper.find('.mt-link').exists()).toBe(false);
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('add-documents')).toEqual([[]]);
    });
});
