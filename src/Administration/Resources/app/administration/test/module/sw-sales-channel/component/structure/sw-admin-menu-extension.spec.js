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
            loginService: {},
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
            }
        },
        mocks: {
            $tc: v => v,
            $device: {
                onResize: () => {},
                getViewportWidth: () => 1920
            }
        }
    });
}

describe('module/sw-sales-channel/component/structure/sw-admin-menu-extension', () => {
    beforeAll(() => {
        Shopware.FeatureConfig.isActive = () => true;
        Shopware.State.get('session').currentUser = {};
        Shopware.Service().register('loginService', () => {
            return {
                notifyOnLoginListener: () => {}
            };
        });
    });

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should not show the sw-sales-channel-menu when privilege does not exists', () => {
        const wrapper = createWrapper();
        const swSalesChannelMenu = wrapper.find('sw-sales-channel-menu-stub');

        expect(swSalesChannelMenu.exists()).toBeFalsy();
    });

    it('should show the sw-sales-channel-menu when privilege exists', () => {
        const wrapper = createWrapper([
            'sales_channel.viewer'
        ]);
        const swSalesChannelMenu = wrapper.find('sw-sales-channel-menu-stub');

        expect(swSalesChannelMenu.exists()).toBeTruthy();
    });
});
