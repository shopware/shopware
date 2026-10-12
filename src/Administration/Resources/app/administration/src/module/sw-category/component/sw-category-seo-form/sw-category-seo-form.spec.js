/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(category = {}) {
    return mount(await wrapTestComponent('sw-category-seo-form', { sync: true }), {
        global: {
            stubs: {
                'sw-text-field': true,
                'mt-textarea': {
                    props: ['maxLength'],
                    template: '<div class="mt-textarea-stub" :max-length="maxLength"><slot name="hint"></slot></div>',
                },
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
        expect(wrapper.get('.mt-textarea-stub').attributes('max-length')).toBe('255');
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

    it('should show the length of the meta title and description against the recommended length', async () => {
        const wrapper = await createWrapper({ metaTitle: 'Title', metaDescription: 'a'.repeat(151) });

        const hints = wrapper.findAll('.sw-category-seo-form__recommended-length');

        expect(hints).toHaveLength(2);
        expect(hints[0].text()).toBe('sw-category.base.seo.recommendedLength');
        expect(hints[0].classes()).not.toContain('is--exceeded');
        expect(hints[1].classes()).toContain('is--exceeded');
        expect(wrapper.vm.metaTitleLength).toBe(5);
        expect(wrapper.vm.metaDescriptionLength).toBe(151);
    });

    it('should count empty values as 0 characters for the recommended length', async () => {
        const wrapper = await createWrapper({ metaTitle: null, metaDescription: undefined });

        expect(wrapper.vm.metaTitleLength).toBe(0);
        expect(wrapper.vm.metaDescriptionLength).toBe(0);
    });
});
