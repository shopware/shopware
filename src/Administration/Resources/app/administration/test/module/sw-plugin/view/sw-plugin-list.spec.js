import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-plugin/view/sw-plugin-list';
import swPluginState from 'src/module/sw-plugin/state/plugin.store';

const createWrapper = () => {
    return shallowMount(Shopware.Component.build('sw-plugin-list'), {
        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot name="grid"></slot></div>'
            },
            'sw-single-select': true,
            'sw-empty-state': true,
            'sw-entity-listing': true
        },
        provide: {
            licenseViolationService: {},
            repositoryFactory: {
                create: () => {}
            }
        },
        propsData: {},
        mocks: {
            $tc: v => v,
            $route: {
                query: () => {}
            },
            $router: {
                replace: () => {}
            },
            $store: Shopware.State._store
        }
    });
};

describe('module/sw-plugin/view/sw-plugin-list', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swPlugin', swPluginState);
    });

    beforeEach(() => {
        Shopware.State.commit('swPlugin/commitPlugins', []);
    });

    it('should be a Vue.js component', async () => {
        Shopware.State.commit('swPlugin/commitPlugins', [{}, {}]);

        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should update the plugins when the listing emit the event "update-records"', async () => {
        // set plugins
        Shopware.State.commit('swPlugin/commitPlugins', [{}, {}]);

        // create wrapper
        const wrapper = await createWrapper();

        // check if only two elements are in list
        expect(wrapper.vm.plugins.length).toBe(2);

        // update records
        const entityListing = wrapper.find('sw-entity-listing-stub');
        entityListing.vm.$emit('update-records', [{}, {}, {}]);

        // check if plugins get updated
        expect(wrapper.vm.plugins.length).toBe(3);
    });
});
