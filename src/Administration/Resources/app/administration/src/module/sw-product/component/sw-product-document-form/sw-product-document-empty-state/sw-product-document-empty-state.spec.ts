/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';

describe('sw-product-document-empty-state', () => {
    const originalLanguage = Shopware.Context.api.languageId;
    let wrapper: VueWrapper;
    let emitEvent: jest.SpyInstance;

    async function createWrapper(canAddDocuments = true, defaultLanguageName = 'English') {
        return shallowMount(await wrapTestComponent('sw-product-document-empty-state', { sync: true }), {
            props: { canAddDocuments, defaultLanguageName },
            global: {
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
        emitEvent = jest.spyOn(Shopware.Utils.EventBus, 'emit');
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
        Shopware.Context.api.languageId = originalLanguage;
    });

    it('shows Add documents in the default language', async () => {
        wrapper = await createWrapper();
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('documents-add')).toEqual([[]]);
        expect(wrapper.find('.mt-link').exists()).toBe(false);
    });

    it('disables adding documents when the product cannot be edited', async () => {
        wrapper = await createWrapper(false);
        expect(wrapper.get<HTMLButtonElement>('button').element.disabled).toBe(true);
    });

    it('offers a switch to the default language when another language is active', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper(false, 'Français');
        await flushPromises();

        expect(wrapper.find('.mt-button').exists()).toBe(false);
        const link = wrapper.get('button.mt-link');
        expect(link.text()).toBe("'Français'");
        await link.trigger('click');
        expect(emitEvent).toHaveBeenCalledWith('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
        expect(wrapper.emitted('documents-add')).toBeUndefined();
    });

    it('keeps language switching available if the language name cannot be loaded', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper(false, '');
        await flushPromises();

        expect(wrapper.get('button.mt-link').text()).toContain('sw-product.documentForm.defaultLanguage');
        await wrapper.get('button.mt-link').trigger('click');
        expect(emitEvent).toHaveBeenCalledWith('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
    });

    it('offers Add documents after switching back to the default language', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        wrapper = await createWrapper();
        await flushPromises();
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        await flushPromises();

        expect(wrapper.find('.mt-link').exists()).toBe(false);
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('documents-add')).toEqual([[]]);
    });
});
