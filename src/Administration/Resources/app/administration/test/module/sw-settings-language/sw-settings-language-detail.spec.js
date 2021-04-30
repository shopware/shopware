import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-language/page/sw-settings-language-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-language-detail'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve();
                    },

                    create: () => {
                        return Promise.resolve({
                            isNew: () => true
                        });
                    },

                    get: () => {
                        return Promise.resolve({
                            isNew: () => false
                        });
                    },

                    save: () => {
                        return Promise.resolve();
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `
            },
            'sw-card-view': true,
            'sw-card': true,
            'sw-container': true,
            'sw-language-switch': true,
            'sw-language-info': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-field': true,
            'sw-entity-single-select': true
        }
    });
}

describe('module/sw-settings-language/page/sw-settings-language-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to save the language', async () => {
        const wrapper = createWrapper([
            'language.editor'
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-language-detail__save-action'
        );
        const languageNameField = wrapper.find(
            'sw-field-stub[label="sw-settings-language.detail.labelName"]'
        );
        const languageParentIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelParent"]'
        );
        const languageTranslationCodeIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelIsoCode"]'
        );
        const languageLocaleIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelLocale"]'
        );

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(languageNameField.attributes().disabled).toBeUndefined();
        expect(languageParentIdField.attributes().disabled).toBeUndefined();
        expect(languageTranslationCodeIdField.attributes().disabled).toBeUndefined();
        expect(languageLocaleIdField.attributes().disabled).toBeUndefined();
    });

    it('should not be able to save the language', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-language-detail__save-action'
        );
        const languageNameField = wrapper.find(
            'sw-field-stub[label="sw-settings-language.detail.labelName"]'
        );
        const languageParentIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelParent"]'
        );
        const languageTranslationCodeIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelIsoCode"]'
        );
        const languageLocaleIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelLocale"]'
        );

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(languageNameField.attributes().disabled).toBeTruthy();
        expect(languageParentIdField.attributes().disabled).toBeTruthy();
        expect(languageTranslationCodeIdField.attributes().disabled).toBeTruthy();
        expect(languageLocaleIdField.attributes().disabled).toBeTruthy();
    });
});
