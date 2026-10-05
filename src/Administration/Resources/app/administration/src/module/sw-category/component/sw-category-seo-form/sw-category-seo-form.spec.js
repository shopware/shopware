/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(category = {}) {
    return mount(await wrapTestComponent('sw-category-seo-form', { sync: true }), {
        global: {
            stubs: {
                'sw-text-field': true,
                'mt-textarea': true,
            },
        },
        props: {
            category,
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

    it('should limit all seo fields to the 255 characters the database can store', async () => {
        const wrapper = await createWrapper({ metaTitle: null, metaDescription: null, keywords: null });

        const inputs = wrapper.findAll('input');
        expect(inputs).toHaveLength(2);
        inputs.forEach((input) => {
            expect(input.attributes('maxlength')).toBe('255');
        });
        expect(wrapper.get('mt-textarea-stub').attributes('max-length')).toBe('255');
        // empty values count as 0 characters, not as the characters of "null" or "undefined"
        expect(wrapper.text().match(/\d+\/255/g)).toEqual(['0/255', '0/255']);
    });

    it('should have an all fields disabled when not having the right acl rights', async () => {
        const wrapper = await createWrapper();

        const textFields = wrapper.findAll('sw-field-stub');

        textFields.forEach((textField) => {
            expect(textField.attributes().disabled).toBe('true');
        });
    });
});
