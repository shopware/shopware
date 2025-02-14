/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper({ template = '<sw-provide />', components = {}, data = {} } = {}) {
    return mount({
        template,
        components: {
            'sw-provide': await wrapTestComponent('sw-provide', { sync: true }),
            ...components,
        },
        data() {
            return data;
        },
    });
}

describe('src/app/component/base/sw-provide', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('renders the children without adding extra HTML', async () => {
        const wrapper = await createWrapper({
            template: `
            <sw-provide>
                <div class="test-child" />
            </sw-provide>`,
        });

        expect(wrapper.html()).toBe('<div class="test-child"></div>');
    });

    it('provides the attributes to the children', async () => {
        const wrapper = await createWrapper({
            template: `
            <sw-provide :foo="42" :bar="true">
                    <child-component>{{ foo }} {{ bar }}</child-component>
            </sw-provide>`,
            components: {
                'child-component': {
                    template: '<div>{{ foo }} {{ bar }}</div>',
                    inject: [
                        'foo',
                        'bar',
                    ],
                },
            },
        });

        expect(wrapper.text()).toBe('42 true');
    });

    it('keeps reactivity of provided attributes', async () => {
        const wrapper = await createWrapper({
            template: `
            <sw-provide :foo="foo">
                <child-component>{{ foo }}</child-component>
            </sw-provide>`,
            components: {
                'child-component': {
                    template: '<div>{{ foo }}</div>',
                    inject: ['foo'],
                },
            },
            data: {
                foo: 'bar',
            },
        });

        expect(wrapper.text()).toBe('bar');
        await wrapper.setData({ foo: 'baz' });
        expect(wrapper.text()).toBe('baz');
    });

    it('converts attrs name to camelCase', async () => {
        const wrapper = await createWrapper({
            template: `
            <sw-provide :foo-bar="42" :barFoo="true">
                <child-component>{{ fooBar }}</child-component>
            </sw-provide>`,
            components: {
                'child-component': {
                    template: '<div>{{ fooBar }} {{ barFoo }}</div>',
                    inject: [
                        'fooBar',
                        'barFoo',
                    ],
                },
            },
        });

        expect(wrapper.text()).toBe('42 true');
    });
});
