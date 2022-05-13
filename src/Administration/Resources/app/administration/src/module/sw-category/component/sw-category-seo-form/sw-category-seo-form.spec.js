import { shallowMount } from '@vue/test-utils';
import swCategorySeoForm from 'src/module/sw-category/component/sw-category-seo-form';

Shopware.Component.register('sw-category-seo-form', swCategorySeoForm);

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-category-seo-form'), {
        stubs: {
            'sw-text-field': true,
            'sw-textarea-field': true,
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
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an all fields enabled when having the right acl rights', async () => {
        const wrapper = await createWrapper([
            'category.editor'
        ]);

        const textFields = wrapper.findAll('sw-field-stub');

        textFields.wrappers.forEach(textField => {
            expect(textField.attributes().disabled).toBeUndefined();
        });
    });

    it('should have an all fields disabled when not having the right acl rights', async () => {
        const wrapper = await createWrapper();

        const textFields = wrapper.findAll('sw-field-stub');

        textFields.wrappers.forEach(textField => {
            expect(textField.attributes().disabled).toBe('true');
        });
    });
});
