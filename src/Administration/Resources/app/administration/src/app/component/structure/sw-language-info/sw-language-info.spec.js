/**
 * @package admin
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';

describe('src/app/component/structure/sw-language-info', () => {
    let wrapper = null;


    beforeEach(async () => {
        Shopware.State.commit('context/setApiLanguageId', '123456789');
        Shopware.State.commit('context/setApiSystemLanguageId', '123456789');
        Shopware.State.commit('context/setApiLanguage', {
            id: '123',
            parentId: '456',
        });

        wrapper = mount(await wrapTestComponent('sw-language-info', { sync: true }), {
            global: {
                mocks: {
                    $tc: (snippetKey, count, args) => {
                        let value = `|${snippetKey}|${count}|`;

                        if (typeof args !== 'object') {
                            return value;
                        }

                        Object.keys(args).forEach((key) => {
                            value += `${key}:${args[key]}|`;
                        });

                        return value;
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            get: () => Promise.resolve({}),
                        }),
                    },
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
        const typeError = {
            method: 'warn',
            msg: '[TypeError: Cannot read properties of null (reading \'id\')]',
        };
        global.allowedErrors.push(typeError);

        Shopware.State.commit('context/setApiLanguage', null);

        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toBe('');

        // To make sure the allowedErrors don't get altered
        const pop = global.allowedErrors.pop();
        expect(pop).toBe(typeError);
    });

    it('should not render the infoText when user is in default language', async () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: null,
        });
        Shopware.State.commit('context/setApiLanguageId', '123');
        Shopware.State.commit('context/setApiSystemLanguageId', '123');

        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toBe('');
    });

    it('should render the infoText for a new entity', async () => {
        await wrapper.setProps({
            isNewEntity: true,
        });

        expect(wrapper.find('.sw_language-info__info').text()).toBe('|sw-language-info.infoTextNewEntity|0|entityDescription:|');
    });

    it('should render the infoText for a child language', async () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: '123',
        });

        expect(wrapper.find('.sw_language-info__info').text()).toBe('|sw-language-info.infoTextChildLanguage|0|entityDescription:|language:undefined|');
    });

    it('should render the infoText for a root language', async () => {
        Shopware.State.commit('context/setApiSystemLanguageId', '987654312');
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: null,
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw_language-info__info').text()).toBe('|sw-language-info.infoTextRootLanguage|0|entityDescription:|language:undefined|');
    });

    it('should render the infoText with entityDescription for a new entity', async () => {
        await wrapper.setProps({
            isNewEntity: true,
            entityDescription: 'My entity description',
        });

        expect(wrapper.find('.sw_language-info__info').text()).toBe('|sw-language-info.infoTextNewEntity|0|entityDescription:My entity description|');
    });

    it('should render the infoText with entityDescription for a child language', async () => {
        Shopware.State.commit('context/setApiLanguage', {
            id: '1a2b3c',
            parentId: '123',
        });

        await wrapper.setProps({
            entityDescription: 'My entity description',
        });

        expect(wrapper.find('.sw_language-info__info').text()).toBe('|sw-language-info.infoTextChildLanguage|0|entityDescription:My entity description|language:undefined|');
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

        expect(wrapper.find('.sw_language-info__info').text()).toBe('|sw-language-info.infoTextRootLanguage|0|entityDescription:My entity description|language:undefined|');
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

        expect(wrapper.find('.sw_language-info__info').text()).toBe('|sw-language-info.infoTextChildLanguage|0|entityDescription:My entity description|language:demoLanguage|');
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

        expect(wrapper.find('.sw_language-info__info').text()).toBe('|sw-language-info.infoTextRootLanguage|0|entityDescription:My entity description|language:demoLanguage|');
    });
});
