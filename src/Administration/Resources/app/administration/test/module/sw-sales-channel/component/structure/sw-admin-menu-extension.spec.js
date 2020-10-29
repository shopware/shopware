import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/structure/sw-admin-menu';
import 'src/module/sw-sales-channel/component/structure/sw-admin-menu-extension';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-admin-menu'), {
        localVue,
        stubs: {
            'sw-version': true,
            'sw-icon': true,
            'sw-loader': true,
            'sw-avatar': true,
            'sw-shortcut-overview': true,
            'sw-sales-channel-menu': true
        },
        provide: {
            loginService: {
                notifyOnLoginListener: () => {}
            },
            userService: {
                getUser: () => Promise.resolve({})
            },
            menuService: {
                getMainMenu: () => []
            },
            acl: {
                can: (privilegeKey) => {
                    if (!privilegeKey) { return true; }

                    return privileges.includes(privilegeKey);
                }
            },
            feature: {
                isActive: () => true
            }
        },
        mocks: {
            $tc: v => v,
            $device: {
                onResize: () => {},
                getViewportWidth: () => 1920
            }
        },
        methods: {
            refreshApps: () => {}
        }
    });
}

describe('module/sw-sales-channel/component/structure/sw-admin-menu-extension', () => {
    beforeAll(() => {
        Shopware.Feature.isActive = () => true;
        Shopware.State.get('session').currentUser = {};
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not show the sw-sales-channel-menu when privilege does not exists', async () => {
        const wrapper = createWrapper();
        const swSalesChannelMenu = wrapper.find('sw-sales-channel-menu-stub');

        expect(swSalesChannelMenu.exists()).toBeFalsy();
    });

    it('should show the sw-sales-channel-menu when privilege exists', async () => {
        const wrapper = createWrapper([
            'sales_channel.viewer'
        ]);
        const swSalesChannelMenu = wrapper.find('sw-sales-channel-menu-stub');

        expect(swSalesChannelMenu.exists()).toBeTruthy();
    });
});
