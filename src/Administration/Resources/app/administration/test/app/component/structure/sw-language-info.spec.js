import { shallowMount, createLocalVue } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/app/component/structure/sw-language-info';

describe('src/app/component/structure/sw-language-info', () => {
    let wrapper = null;

    beforeAll(() => {});

    beforeEach(() => {
        const localVue = createLocalVue();
        localVue.use(Vuex);

        Shopware.State.commit('context/setApiLanguageId', '123456789');
        Shopware.State.commit('context/setApiSystemLanguageId', '123456789');
        Shopware.State.commit('context/setApiLanguage', {
            id: '123',
            parentId: '456'
        });

        wrapper = shallowMount(Shopware.Component.build('sw-language-info'), {
            localVue,
            stubs: {},
            mocks: {
                $store: Shopware.State._store,
                $tc: (v1, v2, v3) => ({ v1, v2, v3 }),
                $sanitize: v => v
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: () => Promise.resolve({})
                    })
                }
            }
        });
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should contain the languageId', () => {
        expect(wrapper.vm.languageId).toBe('123456789');
    });

    it('should not render the infoText when no language is set', () => {
        Shopware.State.commit('context/setApiLanguage', null);

        expect(wrapper.html()).toBeUndefined();
    });

    it('should not render the infoText when user is in default language', () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: null
        });
        Shopware.State.commit('context/setApiLanguageId', '123');
        Shopware.State.commit('context/setApiSystemLanguageId', '123');

        expect(wrapper.html()).toBeUndefined();
    });

    it('should render the infoText for a new entity', () => {
        wrapper.setProps({
            isNewEntity: true
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextNewEntity');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: ''
        });
    });

    it('should render the infoText for a child language', () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: '123'
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextChildLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: ''
        });
    });

    it('should render the infoText for a root language', () => {
        Shopware.State.commit('context/setApiSystemLanguageId', '987654312');
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: null
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextRootLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: ''
        });
    });

    it('should render the infoText with entityDescription for a new entity', () => {
        wrapper.setProps({
            isNewEntity: true,
            entityDescription: 'My entity description'
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextNewEntity');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: 'My entity description'
        });
    });

    it('should render the infoText with entityDescription for a child language', () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: '123'
        });

        wrapper.setProps({
            entityDescription: 'My entity description'
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextChildLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: 'My entity description'
        });
    });

    it('should render the infoText with entityDescription for a root language', () => {
        Shopware.State.commit('context/setApiSystemLanguageId', '987654312');
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: null
        });

        wrapper.setProps({
            entityDescription: 'My entity description'
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextRootLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: 'My entity description'
        });
    });

    it('should render the infoText with language name for a child language', () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            name: 'demoLanguage',
            parentId: '123'
        });

        wrapper.setProps({
            entityDescription: 'My entity description'
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextChildLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: 'My entity description',
            language: 'demoLanguage'
        });
    });

    it('should render the infoText with language name for a root language', () => {
        Shopware.State.commit('context/setApiSystemLanguageId', '987654312');
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            name: 'demoLanguage',
            parentId: null
        });

        wrapper.setProps({
            entityDescription: 'My entity description'
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextRootLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: 'My entity description',
            language: 'demoLanguage'
        });
    });
});
