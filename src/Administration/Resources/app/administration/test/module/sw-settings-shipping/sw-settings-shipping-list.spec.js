import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-settings-shipping/page/sw-settings-shipping-list';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.use(Vuex);

    const shippingMethod = {};
    shippingMethod.getEntityName = () => 'shipping_method';
    shippingMethod.isNew = () => false;

    return shallowMount(Shopware.Component.build('sw-settings-shipping-list'), {
        localVue,
        mocks: {
            $route: {
                query: ''
            }
        },
        provide: {
            repositoryFactory: {
                create: () => ({})
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }

        },
        stubs: {
            'sw-page': {
                template: '<div><slot name="content"></slot><slot name="smart-bar-actions"></slot></div>'
            },
            'sw-button': true,
            'sw-entity-listing': true,
            'sw-empty-state': true
        }
    });
}

describe('module/sw-settings-shipping/page/sw-settings-shipping-list', () => {
    it('should be a vue js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have all fields disabled', async () => {
        const wrapper = createWrapper();

        const entityListing = wrapper.find('sw-entity-listing-stub');
        const button = wrapper.find('sw-button-stub');

        expect(entityListing.attributes()['allow-edit']).toBeFalsy();
        expect(entityListing.attributes()['allow-delete']).toBeFalsy();
        expect(entityListing.attributes()['show-selection']).toBeFalsy();
        expect(button.attributes().disabled).toBe('true');
    });

    it('should have edit fields enabled', async () => {
        const wrapper = createWrapper([
            'shipping.editor'
        ]);

        const entityListing = wrapper.find('sw-entity-listing-stub');
        const button = wrapper.find('sw-button-stub');

        expect(entityListing.attributes()['allow-edit']).toBe('true');
        expect(entityListing.attributes()['allow-delete']).toBeFalsy();
        expect(entityListing.attributes()['show-selection']).toBeFalsy();

        expect(button.attributes().disabled).toBe('true');
    });

    it('should have delete fields enabled', async () => {
        const wrapper = createWrapper([
            'shipping.editor',
            'shipping.deleter'
        ]);

        const entityListing = wrapper.find('sw-entity-listing-stub');
        const button = wrapper.find('sw-button-stub');

        expect(entityListing.attributes()['allow-edit']).toBe('true');
        expect(entityListing.attributes()['allow-delete']).toBe('true');

        expect(button.attributes().disabled).toBe('true');
    });

    it('should have creator fields enabled', async () => {
        const wrapper = createWrapper([
            'shipping.editor',
            'shipping.deleter',
            'shipping.creator'
        ]);

        const entityListing = wrapper.find('sw-entity-listing-stub');
        const button = wrapper.find('sw-button-stub');

        expect(entityListing.attributes()['allow-edit']).toBe('true');
        expect(entityListing.attributes()['allow-delete']).toBe('true');

        expect(button.attributes().disabled).toBeUndefined();
    });
});

