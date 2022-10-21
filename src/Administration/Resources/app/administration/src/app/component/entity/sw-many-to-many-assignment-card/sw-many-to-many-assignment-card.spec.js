import { shallowMount } from '@vue/test-utils';
import 'src/app/component/entity/sw-many-to-many-assignment-card';

function createWrapper(customPropsData = {}) {
    const entityCollection = [];
    entityCollection.context = {
        languageId: '1a2b3c'
    };

    return shallowMount(Shopware.Component.build('sw-many-to-many-assignment-card'), {
        stubs: {
            'sw-card': {
                template: '<div><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-select-base': {
                template: '<div class="sw-select-base"></div>'
            },
            'sw-data-grid': {
                template: '<div><slot name="actions"></slot></div>'
            },
            'sw-context-menu': true,
            'sw-context-menu-item': true
        },
        provide: {
            repositoryFactory: {}
        },
        propsData: {
            columns: [],
            entityCollection: entityCollection,
            localMode: true,
            ...customPropsData
        }
    });
}

describe('src/app/component/entity/sw-many-to-many-assignment-card', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled sw-select-base', async () => {
        const wrapper = createWrapper();

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).not.toBeDefined();
    });

    it('should have an disabled sw-select-base', async () => {
        const wrapper = createWrapper({ disabled: true });

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).toBeDefined();
    });

    it('should have an enabled context menu item', async () => {
        const wrapper = createWrapper();

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).not.toBeDefined();
    });

    it('should have an disabled context menu item', async () => {
        const wrapper = createWrapper({ disabled: true });

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).toBeDefined();
    });
});
