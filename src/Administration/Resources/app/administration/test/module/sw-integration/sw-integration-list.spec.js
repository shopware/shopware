import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-integration/page/sw-integration-list';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-integration-list'), {
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },

        provide: { repositoryFactory: {
            create: () => ({
                search: () => {
                    return Promise.resolve([
                        {
                            id: '44de136acf314e7184401d36406c1e90'
                        }
                    ]);
                }
            })
        },

        integrationService: {},
        validationService: {},
        shortcutService: {
            stopEventListener: () => { }
        },

        acl: {
            can: (identifier) => {
                if (!identifier) {
                    return true;
                }

                return privileges.includes(identifier);
            }
        } },

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
            'sw-card-view': {
                template: `
                    <div class="sw-card-view">
                        <slot></slot>
                    </div>
                `
            },
            'sw-card': {
                template: `
                    <div class="sw-card">
                        <slot></slot>
                    </div>
                `
            },
            'sw-modal': Shopware.Component.build('sw-modal'),
            'sw-language-switch': true,
            'sw-search-bar': true,
            'sw-context-menu-item': {
                template: '<div class="sw-context-menu-item"><div class="sw-context-menu-item__text"></div></div>'
            },
            'sw-icon': true,
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-container': {
                template: '<div><slot></slot></div>'
            },
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-field-error': true,
            'sw-field-copyable': true,
            'sw-switch-field': true,
            'sw-entity-multi-select': true,
            'sw-empty-state': {
                template: '<div class="sw-empty-state"></div>'
            },
            'sw-entity-listing': {
                props: ['items', 'detailRoute'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }">
                            </slot>
                        </template>
                    </div>
                `
            }
        }
    });
}

describe('module/sw-settings-country/page/sw-settings-country-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });
});

describe('when has privilege', () => {
    it('should be able to create a integration', async () => {
        const wrapper = createWrapper([
            'integration.creator'
        ]);

        await wrapper.vm.$nextTick();
        const createButton = wrapper.find('.sw-integration-list__add-integration-action');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should be able to edit a integration', async () => {
        const wrapper = createWrapper([
            'integration.editor'
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            currentIntegration: true
        });

        [
            wrapper.find('.sw_integration_list__edit-action'),
            wrapper.find('.sw-integration-detail-modal__save-action'),
            wrapper.find('#sw-field--currentIntegration-label'),
            wrapper.find('.sw-button--danger')
        ].forEach(element => {
            expect(element.attributes().disabled).toBeFalsy();
        });
    });

    it('should be able to delete a integration', async () => {
        const wrapper = createWrapper([
            'integration.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw_integration_list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });
});

describe('when has not privilege', () => {
    it('should not be able to create a integration', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();
        const createButton = wrapper.find('.sw-integration-list__add-integration-action');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should not be able to create a integration', async () => {
        const wrapper = createWrapper([
            'integration.editor',
            'integration.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-integration-list__add-integration-action');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should not be able to edit a integration', async () => {
        const wrapper = await createWrapper([]);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            currentIntegration: true
        });

        [
            wrapper.find('.sw_integration_list__edit-action'),
            wrapper.find('.sw-integration-detail-modal__save-action'),
            wrapper.find('#sw-field--currentIntegration-label'),
            wrapper.find('.sw-button--danger')
        ].forEach(element => {
            expect(element.attributes().disabled).toBeTruthy();
        });
    });

    it('should not be able to edit a integration', async () => {
        const wrapper = await createWrapper([
            'integration.viewer',
            'integration.deleter'
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            currentIntegration: true
        });

        [
            wrapper.find('.sw_integration_list__edit-action'),
            wrapper.find('.sw-integration-detail-modal__save-action'),
            wrapper.find('#sw-field--currentIntegration-label'),
            wrapper.find('.sw-button--danger')
        ].forEach(element => {
            expect(element.attributes().disabled).toBeTruthy();
        });
    });

    it('should not be able to delete a integration', async () => {
        const wrapper = createWrapper([]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw_integration_list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should not be able to delete a integration', async () => {
        const wrapper = createWrapper([
            'integration.viewer',
            'integration.editor'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw_integration_list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should not be able add an integration with admin-role as a non-admin', async () => {
        const wrapper = await createWrapper([
            'integration.viewer',
            'integration.editor',
            'integration.deleter'
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            currentIntegration: true
        });

        const adminRoleSwitch = wrapper.find('.sw-settings-user-detail__grid-is-admin');

        expect(adminRoleSwitch.attributes().disabled).toBeTruthy();
    });
});
