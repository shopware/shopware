import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/structure/sw-sales-channel-menu';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-sales-channel-menu'), {
        localVue,
        stubs: {
            'sw-icon': true
        },
        provide: {
            acl: {
                can: (privilegeKey) => {
                    if (!privilegeKey) { return true; }

                    return privileges.includes(privilegeKey);
                }
            },
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve({})
                })
            }
        },
        mocks: {
            $tc: v => v
        }
    });
}

describe('module/sw-sales-channel/component/structure/sw-admin-menu-extension', () => {
    beforeAll(() => {});

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to create sales channels when user has the privilege', async () => {
        const wrapper = createWrapper([
            'sales_channel.creator'
        ]);

        const buttonCreateSalesChannel = wrapper.find('.sw-admin-menu__headline-action');
        expect(buttonCreateSalesChannel.exists()).toBeTruthy();
    });

    it('should not be able to create sales channels when user has not the privilege', async () => {
        const wrapper = createWrapper();

        const buttonCreateSalesChannel = wrapper.find('.sw-admin-menu__headline-action');
        expect(buttonCreateSalesChannel.exists()).toBeFalsy();
    });
});
