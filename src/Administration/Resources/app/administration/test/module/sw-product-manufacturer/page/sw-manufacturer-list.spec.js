import { createLocalVue, mount } from '@vue/test-utils';
import 'src/module/sw-manufacturer/page/sw-manufacturer-list';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return mount(Shopware.Component.build('sw-manufacturer-list'), {
        localVue,
        stubs: {
            'sw-page': {
                template: '<div><slot name="smart-bar-actions"></slot><slot name="content">CONTENT</slot></div>'
            },
            'sw-entity-listing': {
                props: ['items', 'allowEdit', 'allowDelete'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`
            },
            'sw-empty-state': true,
            'sw-button': true,
            'sw-loader': true
        },
        provide: {
            acl: {
                can: key => (key ? privileges.includes(key) : true)
            },
            stateStyleDataProviderService: {},
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve([]) })
            }
        },
        mocks: {
            $tc: v => v,
            $route: { query: '' },
            $router: {
                replace: () => {
                }
            }
        }
    });
}

describe('src/module/sw-manufacturer/page/sw-manufacturer-list', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled create button', async () => {
        const wrapper = createWrapper(['product_manufacturer.creator']);
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');
        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled create button', async () => {
        const wrapper = createWrapper();
        const addButton = wrapper.find('.sw-manufacturer-list__add-manufacturer');

        expect(addButton.attributes().disabled).toBe('true');
    });

    it('should be able to inline edit', async () => {
        const wrapper = createWrapper([
            'product_manufacturer.editor'
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.props().allowEdit).toBeTruthy();
    });

    it('should not be able to inline edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.props().allowEdit).toBeFalsy();
    });

    it('should be able to inline delete', async () => {
        const wrapper = createWrapper([
            'product_manufacturer.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.props().allowDelete).toBeTruthy();
    });

    it('should not be able to inline delete', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-manufacturer-list__grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.props().allowDelete).toBeFalsy();
    });
});
