/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';

describe('sw-product-document-language-value', () => {
    let wrapper: VueWrapper;
    const api = Shopware.Store.get('context').api;
    const originalLanguageId = api.languageId;
    const originalLanguage = api.language;

    afterEach(() => {
        wrapper?.unmount();
        api.languageId = originalLanguageId;
        api.language = originalLanguage;
    });

    it('hides a stale language badge until the active language has loaded', async () => {
        const frenchLanguageId = repositoryFactory.create('language').create().id;
        const germanLanguageId = repositoryFactory.create('language').create().id;
        api.languageId = frenchLanguageId;
        Object.assign(api, { language: { id: germanLanguageId, locale: { code: 'de-DE' } } });
        wrapper = shallowMount(await wrapTestComponent('sw-product-document-language-value', { sync: true }), {
            props: { value: 'Manuel français', isTranslated: true },
        });
        expect(wrapper.text()).toContain('Manuel français');
        expect(wrapper.find('.mt-badge').exists()).toBe(false);

        Object.assign(api, { language: { id: frenchLanguageId, locale: { code: 'fr-FR' } } });
        await flushPromises();
        expect(wrapper.get('.mt-badge').text()).toBe('FR');
    });
});
