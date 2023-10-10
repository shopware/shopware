/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils_v3';

async function createWrapper(privileges = [], languageId = null) {
    return mount(await wrapTestComponent('sw-settings-language-detail', {
        sync: true,
    }), {
        props: {
            languageId,
        },
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $tc(translationKey) {
                    return translationKey;
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve(
                                {
                                    aggregations: {
                                        usedLocales: {
                                            buckets: [],
                                        },
                                    },
                                },
                            );
                        },

                        create: () => {
                            return Promise.resolve({
                                isNew: () => true,
                            });
                        },

                        get: (id) => {
                            return Promise.resolve({
                                id,
                                isNew: () => false,
                                parentId: '1234',
                                translationCodeId: '5678',
                            });
                        },

                        save: () => {
                            return Promise.resolve();
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
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
                `,
                },
                'sw-card-view': true,
                'sw-card': true,
                'sw-container': true,
                'sw-language-switch': true,
                'sw-language-info': true,
                'sw-button': true,
                'sw-button-process': true,
                'sw-text-field': true,
                'sw-entity-single-select': true,
                'sw-skeleton': true,
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                'sw-inheritance-switch': true,
            },
        },
    });
}

describe('module/sw-settings-language/page/sw-settings-language-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should return identifier', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.identifier).toBe('');

        wrapper.vm.language = {
            name: 'English',
        };

        expect(wrapper.vm.identifier).toBe('English');
    });

    it('should not be possible to inherit with no system language', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.inheritanceTooltipText).toBe('sw-settings-language.detail.tooltipLanguageNotChoosable');

        wrapper.vm.language = {
            id: Shopware.Context.api.systemLanguageId,
        };
        expect(wrapper.vm.inheritanceTooltipText).toBe('sw-settings-language.detail.tooltipInheritanceNotPossible');
    });

    it('should load entity data', async () => {
        const wrapper = await createWrapper([], Shopware.Context.api.systemLanguageId);
        expect(wrapper.vm.languageId).toBe(Shopware.Context.api.systemLanguageId);
        await flushPromises();

        expect(wrapper.vm.language.id).toBe(Shopware.Context.api.systemLanguageId);
    });

    it('should be able to save the language', async () => {
        const wrapper = await createWrapper([
            'language.editor',
        ]);
        await flushPromises();

        const saveButton = wrapper.find(
            '.sw-settings-language-detail__save-action',
        );
        const languageNameField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-language.detail.labelName"]',
        );
        const languageParentIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelParent"]',
        );
        const languageTranslationCodeIdField = wrapper.find(
            '#iso-codes',
        );
        const languageLocaleIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelLocale"]',
        );

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(languageNameField.attributes().disabled).toBeUndefined();
        expect(languageParentIdField.attributes().disabled).toBeUndefined();
        expect(languageTranslationCodeIdField.attributes().disabled).toBeUndefined();
        expect(languageLocaleIdField.attributes().disabled).toBeUndefined();
    });

    it('should not be able to save the language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find(
            '.sw-settings-language-detail__save-action',
        );
        const languageNameField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-language.detail.labelName"]',
        );
        const languageParentIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelParent"]',
        );
        const languageTranslationCodeIdField = wrapper.find(
            '#iso-codes',
        );
        const languageLocaleIdField = wrapper.find(
            'sw-entity-single-select-stub[label="sw-settings-language.detail.labelLocale"]',
        );

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(languageNameField.attributes().disabled).toBeTruthy();
        expect(languageParentIdField.attributes().disabled).toBeTruthy();
        expect(languageTranslationCodeIdField.attributes().disabled).toBeTruthy();
        expect(languageLocaleIdField.attributes().disabled).toBeTruthy();
    });
});
