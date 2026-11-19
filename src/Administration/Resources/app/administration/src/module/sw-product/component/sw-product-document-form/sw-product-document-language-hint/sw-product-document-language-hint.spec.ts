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

    async function createWrapper(defaultLanguageName = '') {
        wrapper = shallowMount(await wrapTestComponent('sw-product-document-language-hint', { sync: true }), {
            props: { defaultLanguageName },
            global: {
                stubs: { 'i18n-t': { template: '<span><slot name="language" /></span>' } },
            },
        });
        await flushPromises();
        return wrapper;
    }

    it('shows the default language name and requests a language switch when its link is clicked', async () => {
        await createWrapper('English');
        expect(wrapper.text()).toContain('English');
        await wrapper.get('button').trigger('click');
        expect(emitEvent).toHaveBeenCalledWith('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
    });

    it('keeps the language switch available when no language name is provided', async () => {
        await createWrapper();
        expect(wrapper.text()).toContain('sw-product.documentForm.defaultLanguage');
        await wrapper.get('button').trigger('click');
        expect(emitEvent).toHaveBeenCalledWith('on-change-language-clicked', Shopware.Context.api.systemLanguageId);
    });
});
