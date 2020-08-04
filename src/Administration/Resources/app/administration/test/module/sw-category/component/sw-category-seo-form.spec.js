import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-category-seo-form';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-category-seo-form'), {
        localVue,
        stubs: {
            'sw-field': true
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

describe('src/module/sw-category/component/sw-category-seo-form', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should have an all fields enabled when having the right acl rights', () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const textFields = wrapper.findAll('sw-field-stub');

        textFields.wrappers.forEach(textField => {
            expect(textField.attributes().disabled).toBeUndefined();
        });
    });

    it('should have an all fields disabled when not having the right acl rights', () => {
        const wrapper = createWrapper();

        const textFields = wrapper.findAll('sw-field-stub');

        textFields.wrappers.forEach(textField => {
            expect(textField.attributes().disabled).toBe('true');
        });
    });
});
