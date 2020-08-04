import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-category-link-settings';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-category-link-settings'), {
        localVue,
        stubs: {
            'sw-card': true,
            'sw-text-field': true
        },
        mocks: {
            $route: {
                params: {}
            },
            $tc: v => v
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        propsData: {
            category: {}
        }
    });
}

describe('src/module/sw-category/component/sw-category-link-settings', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should have an enabled text field for the external link', () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const textField = wrapper.find('sw-text-field-stub');
        expect(textField.attributes().disabled).toBeUndefined();
    });

    it('should have an enabled text field for the external link', () => {
        const wrapper = createWrapper();

        const textField = wrapper.find('sw-text-field-stub');
        expect(textField.attributes().disabled).toBe('true');
    });
});
