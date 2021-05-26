import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-review/page/sw-review-list';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-review-list'), {
        localVue,
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return Promise.resolve([{
                            id: '1a2b3c',
                            entity: 'review',
                            customerId: 'd4c3b2a1',
                            productId: 'd4c3b2a1',
                            salesChannelId: 'd4c3b2a1'
                        }]);
                    },
                    search: () => {
                        return Promise.resolve([{
                            id: '1a2b3c',
                            entity: 'review',
                            customerId: 'd4c3b2a1',
                            productId: 'd4c3b2a1',
                            salesChannelId: 'd4c3b2a1',
                            sourceEntitiy: 'product-review'
                        }]);
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
            }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`
            },
            'sw-button': true,
            'sw-icon': true,
            'sw-search-bar': true,
            'sw-entity-listing': true,
            'sw-language-switch': true,
            'sw-empty-state': true,
            'sw-context-menu-item': true
        }
    });
}

describe('module/sw-review/page/sw-review-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to delete', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('sw-entity-listing-stub');
        expect(deleteMenuItem.attributes()['allow-delete']).toBeFalsy();
    });

    it('should be able to delete', async () => {
        const wrapper = createWrapper([
            'review.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('sw-entity-listing-stub');
        expect(deleteMenuItem.attributes()['allow-delete']).toBeTruthy();
    });

    it('should not be able to edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('sw-entity-listing-stub');
        expect(editMenuItem.attributes()['allow-edit']).toBeFalsy();
    });

    it('should be able to edit', async () => {
        const wrapper = createWrapper([
            'review.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('sw-entity-listing-stub');
        expect(editMenuItem.attributes()['allow-edit']).toBeTruthy();
    });
});
