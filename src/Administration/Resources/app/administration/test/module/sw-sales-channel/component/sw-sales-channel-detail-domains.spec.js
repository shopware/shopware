import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-detail-domains';

function createWrapper(customProps = {}) {
    return shallowMount(Shopware.Component.build('sw-sales-channel-detail-domains'), {
        stubs: {
            'sw-card': {
                template: '<div><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-button': true,
            'sw-data-grid': {
                template: '<div><slot name="actions"></slot></div>'
            },
            'sw-context-menu-item': true
        },
        provide: {
            repositoryFactory: {}
        },
        mocks: {
            $tc: v => v,
            $t: () => true
        },
        propsData: {
            salesChannel: {},
            ...customProps
        }
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-detail-domains', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have button enabled', async () => {
        const wrapper = createWrapper();

        const button = wrapper.find('.sw-sales-channel-detail__button-domain-add');

        expect(button.attributes().disabled).toBeUndefined();
    });

    it('should have button disabled', async () => {
        const wrapper = createWrapper({
            disableEdit: true
        });

        const button = wrapper.find('.sw-sales-channel-detail__button-domain-add');

        expect(button.attributes().disabled).toBe('true');
    });

    it('should have context menu item enabled', async () => {
        const wrapper = createWrapper();

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');

        contextMenuItems.wrappers.forEach(item => {
            expect(item.attributes().disabled).toBeUndefined();
        });
    });

    it('should have context menu item disabled', async () => {
        const wrapper = createWrapper({
            disableEdit: true
        });

        const contextMenuItems = wrapper.findAll('sw-context-menu-item-stub');

        contextMenuItems.wrappers.forEach(item => {
            expect(item.attributes().disabled).toBe('true');
        });
    });
});
