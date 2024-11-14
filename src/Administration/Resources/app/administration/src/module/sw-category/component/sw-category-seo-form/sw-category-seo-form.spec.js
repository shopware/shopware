/**
 * @package inventory
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-category-seo-form', { sync: true }), {
        global: {
            stubs: {
                'sw-text-field': true,
                'sw-textarea-field': true,
            },
        },
        props: {
            category: {},
        },
    });
}

describe('src/module/sw-category/component/sw-category-seo-form', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should have an all fields enabled when having the right acl rights', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const textFields = wrapper.findAll('sw-field-stub');

        textFields.forEach((textField) => {
            expect(textField.attributes().disabled).toBeUndefined();
        });
    });

    it('should have an all fields disabled when not having the right acl rights', async () => {
        const wrapper = await createWrapper();

        const textFields = wrapper.findAll('sw-field-stub');

        textFields.forEach((textField) => {
            expect(textField.attributes().disabled).toBe('true');
        });
    });
});
