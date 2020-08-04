import { shallowMount } from '@vue/test-utils';
import 'src/app/component/entity/sw-many-to-many-assignment-card';

function createWrapper(customPropsData = {}) {
    const entityCollection = [];
    entityCollection.context = {
        languageId: '1a2b3c'
    };

    return shallowMount(Shopware.Component.build('sw-many-to-many-assignment-card'), {
        stubs: {
            'sw-card': '<div><slot></slot><slot name="grid"></slot></div>',
            'sw-select-base': '<div class="sw-select-base"></div>',
            'sw-data-grid': '<div><slot name="actions"></slot></div>',
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
        },
        mocks: {
            $tc: v => v
        }
    });
}

describe('src/app/component/entity/sw-many-to-many-assignment-card', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should have an enabled sw-select-base', () => {
        const wrapper = createWrapper();

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).not.toBeDefined();
    });

    it('should have an disabled sw-select-base', () => {
        const wrapper = createWrapper({ disabled: true });

        const selectBase = wrapper.find('.sw-select-base');

        expect(selectBase.attributes().disabled).toBeDefined();
    });

    it('should have an enabled context menu item', () => {
        const wrapper = createWrapper();

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).not.toBeDefined();
    });

    it('should have an disabled context menu item', () => {
        const wrapper = createWrapper({ disabled: true });

        const selectBase = wrapper.find('sw-context-menu-item-stub');

        expect(selectBase.attributes().disabled).toBeDefined();
    });
});
