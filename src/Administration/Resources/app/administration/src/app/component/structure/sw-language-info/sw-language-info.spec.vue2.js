/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils_v2';
import 'src/app/component/structure/sw-language-info';

describe('src/app/component/structure/sw-language-info', () => {
    let wrapper = null;


    beforeEach(async () => {
        Shopware.State.commit('context/setApiLanguageId', '123456789');
        Shopware.State.commit('context/setApiSystemLanguageId', '123456789');
        Shopware.State.commit('context/setApiLanguage', {
            id: '123',
            parentId: '456',
        });

        wrapper = shallowMount(await Shopware.Component.build('sw-language-info'), {
            stubs: {},
            mocks: {
                $tc: (v1, v2, v3) => ({ v1, v2, v3 }),
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: () => Promise.resolve({}),
                    }),
                },
            },
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the languageId', async () => {
        expect(wrapper.vm.languageId).toBe('123456789');
    });

    it('should not render the infoText when no language is set', async () => {
        Shopware.State.commit('context/setApiLanguage', null);

        await wrapper.vm.$nextTick();

        expect(wrapper.html()).toBe('');
    });

    it('should not render the infoText when user is in default language', async () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: null,
        });
        Shopware.State.commit('context/setApiLanguageId', '123');
        Shopware.State.commit('context/setApiSystemLanguageId', '123');

        await wrapper.vm.$nextTick();

        expect(wrapper.html()).toBe('');
    });

    it('should render the infoText for a new entity', async () => {
        await wrapper.setProps({
            isNewEntity: true,
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextNewEntity');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: '',
        });
    });

    it('should render the infoText for a child language', async () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: '123',
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextChildLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: '',
        });
    });

    it('should render the infoText for a root language', async () => {
        Shopware.State.commit('context/setApiSystemLanguageId', '987654312');
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: null,
        });

        await wrapper.vm.$nextTick();

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextRootLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: '',
        });
    });

    it('should render the infoText with entityDescription for a new entity', async () => {
        await wrapper.setProps({
            isNewEntity: true,
            entityDescription: 'My entity description',
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextNewEntity');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: 'My entity description',
        });
    });

    it('should render the infoText with entityDescription for a child language', async () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: '123',
        });

        await wrapper.setProps({
            entityDescription: 'My entity description',
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextChildLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: 'My entity description',
        });
    });

    it('should render the infoText with entityDescription for a root language', async () => {
        Shopware.State.commit('context/setApiSystemLanguageId', '987654312');
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: null,
        });

        await wrapper.setProps({
            entityDescription: 'My entity description',
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextRootLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: 'My entity description',
        });
    });

    it('should render the infoText with language name for a child language', async () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            name: 'demoLanguage',
            parentId: '123',
        });

        await wrapper.setProps({
            entityDescription: 'My entity description',
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextChildLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: 'My entity description',
            language: 'demoLanguage',
        });
    });

    it('should render the infoText with language name for a root language', async () => {
        Shopware.State.commit('context/setApiSystemLanguageId', '987654312');
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            name: 'demoLanguage',
            parentId: null,
        });

        await wrapper.setProps({
            entityDescription: 'My entity description',
        });

        const infoText = JSON.parse(wrapper.find('.sw_language-info__info').text());
        expect(infoText.v1).toBe('sw-language-info.infoTextRootLanguage');
        expect(infoText.v2).toBe(0);
        expect(infoText.v3).toEqual({
            entityDescription: 'My entity description',
            language: 'demoLanguage',
        });
    });
});
