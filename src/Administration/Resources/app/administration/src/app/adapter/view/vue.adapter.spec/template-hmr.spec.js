/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { h } from 'vue';
import VueAdapter from 'src/app/adapter/view/vue.adapter';
import { createApplication, prepareVueAdapterEnvironment, resetVueAdapterTestState } from './vue-adapter-test-setup';

describe('app/adapter/view/vue.adapter.js - template hot updates', () => {
    let vueAdapter;
    let wrappers;

    async function mountComponent(componentName) {
        Shopware.Component.markComponentAsSync(componentName);
        const component = await vueAdapter.createComponent(componentName);
        const wrapper = mount(component);
        wrappers.push(wrapper);

        return wrapper;
    }

    beforeEach(() => {
        prepareVueAdapterEnvironment();
        vueAdapter = new VueAdapter(createApplication());
        wrappers = [];
    });

    afterEach(() => {
        wrappers.forEach((wrapper) => wrapper.unmount());
        resetVueAdapterTestState();
    });

    it('updates a registered template in place and keeps the component state', async () => {
        const template =
            '<div>{% block hmr_state_text %}<span class="text">Before</span>{% endblock %}<input v-model="draft"></div>';
        Shopware.Component.register('hmr-state', {
            template,
            data: () => ({ draft: '' }),
        });
        const wrapper = await mountComponent('hmr-state');
        await wrapper.find('input').setValue('unsaved draft');

        const updated = await vueAdapter.hotUpdateTemplate(template, template.replace('Before', 'After'));
        await flushPromises();

        expect(updated).toBe(true);
        expect(wrapper.find('.text').text()).toBe('After');
        expect(wrapper.find('input').element.value).toBe('unsaved draft');
    });

    it('updates components that extend the edited template', async () => {
        const template = '<div>{% block hmr_parent_text %}<span class="text">Before</span>{% endblock %}</div>';
        Shopware.Component.register('hmr-parent', { template });
        Shopware.Component.extend('hmr-child', 'hmr-parent', {
            template: '{% block hmr_parent_text %}{% parent %}<span class="child">Child</span>{% endblock %}',
        });
        const parent = await mountComponent('hmr-parent');
        const child = await mountComponent('hmr-child');

        await vueAdapter.hotUpdateTemplate(template, template.replace('Before', 'After'));
        await flushPromises();

        expect(parent.text()).toBe('After');
        expect(child.text()).toBe('AfterChild');
    });

    it('updates an extending component when only the extending one is rendered', async () => {
        const template = '<div>{% block hmr_base_text %}<span class="text">Before</span>{% endblock %}</div>';
        Shopware.Component.register('hmr-base', { template });
        Shopware.Component.extend('hmr-only-child', 'hmr-base', {
            template: '{% block hmr_base_text %}{% parent %}<span class="child">Child</span>{% endblock %}',
        });
        const child = await mountComponent('hmr-only-child');

        const updated = await vueAdapter.hotUpdateTemplate(template, template.replace('Before', 'After'));
        await flushPromises();

        expect(updated).toBe(true);
        expect(child.text()).toBe('AfterChild');
    });

    it('updates an edited template override on every save', async () => {
        Shopware.Component.register('hmr-overridden', {
            template: '<div>{% block hmr_overridden_text %}Original{% endblock %}</div>',
        });
        const overrideTemplate = '{% block hmr_overridden_text %}Override before{% endblock %}';
        const firstEdit = overrideTemplate.replace('before', 'after');
        Shopware.Component.override('hmr-overridden', { template: overrideTemplate });
        const wrapper = await mountComponent('hmr-overridden');

        const updated = await vueAdapter.hotUpdateTemplate(overrideTemplate, firstEdit);
        await flushPromises();

        expect(updated).toBe(true);
        expect(wrapper.text()).toBe('Override after');

        await vueAdapter.hotUpdateTemplate(firstEdit, firstEdit.replace('after', 'again'));
        await flushPromises();

        expect(wrapper.text()).toBe('Override again');
    });

    it('keeps the template override of an extension when the overridden template is edited', async () => {
        const template =
            '<div>{% block hmr_extended_text %}<span class="text">Before</span>{% endblock %}<input v-model="draft"></div>';
        Shopware.Component.register('hmr-extended', {
            template,
            data: () => ({ draft: '' }),
        });
        Shopware.Component.override('hmr-extended', {
            template: '{% block hmr_extended_text %}{% parent %}<span class="extension">Extension</span>{% endblock %}',
        });
        const wrapper = await mountComponent('hmr-extended');
        await wrapper.find('input').setValue('unsaved draft');

        const updated = await vueAdapter.hotUpdateTemplate(template, template.replace('Before', 'After'));
        await flushPromises();

        expect(updated).toBe(true);
        expect(wrapper.find('.text').text()).toBe('After');
        expect(wrapper.find('.extension').text()).toBe('Extension');
        expect(wrapper.find('input').element.value).toBe('unsaved draft');
    });

    it('applies further edits of the same template', async () => {
        const first = '<div>First</div>';
        const second = '<div>Second</div>';
        Shopware.Component.register('hmr-repeated', { template: first });
        const wrapper = await mountComponent('hmr-repeated');

        await vueAdapter.hotUpdateTemplate(first, second);
        await vueAdapter.hotUpdateTemplate(second, '<div>Third</div>');
        await flushPromises();

        expect(wrapper.text()).toBe('Third');
    });

    it('applies an edit back to an earlier version and a save without a change', async () => {
        const first = '<div>Reverted first</div>';
        const second = '<div>Reverted second</div>';
        Shopware.Component.register('hmr-reverted', { template: first });
        const wrapper = await mountComponent('hmr-reverted');

        await vueAdapter.hotUpdateTemplate(first, second);
        await vueAdapter.hotUpdateTemplate(second, first);
        await vueAdapter.hotUpdateTemplate(first, first);
        const rebuilt = await Shopware.Component.build('hmr-reverted');
        await flushPromises();

        expect(wrapper.text()).toBe('Reverted first');
        expect(rebuilt.template).toBe(first);
    });

    it('asks for a page reload when the template is unknown', async () => {
        expect(await vueAdapter.hotUpdateTemplate('<div>Unknown</div>', '<div>Changed</div>')).toBe(false);
    });

    it('asks for a page reload when two components use the same template', async () => {
        const template = '<div>Shared</div>';
        Shopware.Component.register('hmr-shared-a', { template });
        Shopware.Component.register('hmr-shared-b', { template });
        const first = await mountComponent('hmr-shared-a');
        const second = await mountComponent('hmr-shared-b');

        expect(await vueAdapter.hotUpdateTemplate(template, '<div>Changed</div>')).toBe(false);
        expect(first.text()).toBe('Shared');
        expect(second.text()).toBe('Shared');
    });

    it('asks for a page reload when the edited component was not created by the view', async () => {
        Shopware.Component.register('hmr-not-created', { template: '<div>{% block hmr_not_created %}{% endblock %}</div>' });
        const overrideTemplate = '{% block hmr_not_created %}Before{% endblock %}';
        Shopware.Component.override('hmr-not-created', { template: overrideTemplate });
        await Shopware.Component.build('hmr-not-created');

        expect(await vueAdapter.hotUpdateTemplate(overrideTemplate, '{% block hmr_not_created %}After{% endblock %}')).toBe(
            false,
        );
    });

    it('asks for a page reload when a Twig override targets a component without a template', async () => {
        Shopware.Component.register('hmr-native', {
            render: () => h('div', 'Native'),
        });
        const overrideTemplate = '{% block hmr_native_block %}Override{% endblock %}';
        Shopware.Component.override('hmr-native', { template: overrideTemplate });
        const wrapper = await mountComponent('hmr-native');

        const updated = await vueAdapter.hotUpdateTemplate(overrideTemplate, '{% block hmr_native_block %}{% endblock %}');

        expect(updated).toBe(false);
        expect(wrapper.text()).toBe('Native');
    });
});
