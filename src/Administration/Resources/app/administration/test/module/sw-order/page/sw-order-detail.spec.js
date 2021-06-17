import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/page/sw-order-detail';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-order-detail'), {
        mocks: {
            $route: {
                meta: {
                    $module: {
                        routes: {
                            detail: {
                                children: {
                                    base: {},
                                    other: {}
                                }
                            }
                        }
                    }
                }
            }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div>
                        <slot name="smart-bar-header"></slot>
                        <slot name="smart-bar-actions"></slot>
                    </div>`
            },
            'sw-button': true,
            'sw-label': true
        },
        propsData: {
            orderId: Shopware.Utils.createId()
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([])
                })
            }
        }
    });
}

describe('src/module/sw-order/page/sw-order-detail', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled edit button', async () => {
        await wrapper.setData({ isLoading: false });

        const editButton = wrapper.find('.sw-order-detail__smart-bar-edit-button');

        expect(editButton.attributes().disabled).toBe('true');
    });

    it('should have an enabled edit button', async () => {
        wrapper = createWrapper(['order.editor']);
        await wrapper.setData({ isLoading: false });

        const editButton = wrapper.find('.sw-order-detail__smart-bar-edit-button');

        expect(editButton.attributes().disabled).toBeUndefined();
    });

    it('should not contain manual label', async () => {
        expect(wrapper.find('.sw-order-detail__manual-order-label').exists()).toBeFalsy();
    });

    it('should contain manual label', async () => {
        await wrapper.setData({ identifier: '1', createdById: '2' });

        expect(wrapper.find('.sw-order-detail__manual-order-label').exists()).toBeTruthy();
    });
});
