import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-review/page/sw-review-list';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-review-list'), {
        localVue,
        mocks: {
            $tc: () => {},
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            },
            $router: {
                replace: () => {}
            }
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([
                            {
                                id: '1a2b3c4e',
                                name: 'Test review',
                                sourceEntitiy: 'review'
                            }
                        ]);
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        stubs: {
            'sw-page': `
                <div class="sw-page">
                    <slot name="smart-bar-actions"></slot>
                    <slot name="content">CONTENT</slot>
                    <slot></slot>
                </div>`,
            'sw-button': true,
            'sw-icon': true,
            'sw-search-bar': true,
            'sw-entity-listing': {
                props: ['items'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`
            },
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

        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should not be able to delete', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-review-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        const wrapper = createWrapper([
            'review.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-review-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-review-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const wrapper = createWrapper([
            'review.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-review-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });
});
