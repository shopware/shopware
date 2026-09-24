/**
 * @sw-package framework
 * @group disabledCompat
 */
import { defineAsyncComponent, nextTick } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import createDataScopeFixture from '../sw-block-override.spec/test-utils/create-data-scope-fixture';

async function mountBlock({ name, override, data = {}, components = {} }) {
    return mount(
        {
            template: `
                <div>
                    <sw-block extends="${name}">${override}</sw-block>
                    <div class="root">
                        <sw-block name="${name}">
                            <span class="default">{{ label }}</span>
                        </sw-block>
                    </div>
                </div>
            `,
            components: {
                'sw-block': await wrapTestComponent('sw-block', { sync: true }),
                'sw-block-parent': await wrapTestComponent('sw-block-parent', { sync: true }),
                ...components,
            },
            data: () => ({ label: 'default', ...data }),
        },
        {
            global: { plugins: [createDataScopeFixture()] },
        },
    );
}

describe('sw-block-parent', () => {
    it('renders nothing outside of a block layer', async () => {
        const wrapper = mount(await wrapTestComponent('sw-block-parent', { sync: true }));

        expect(wrapper.find('*').exists()).toBe(false);
    });

    it('renders the parent layer when it is toggled by v-if', async () => {
        const wrapper = await mountBlock({
            name: 'parent-under-v-if',
            override: '<sw-block-parent v-if="showParent" /><b class="override">override</b>',
            data: { showParent: false },
        });

        expect(wrapper.find('.default').exists()).toBe(false);

        await wrapper.setData({ showParent: true });
        await wrapper.setData({ showParent: false });
        await wrapper.setData({ showParent: true });

        expect(wrapper.findAll('.default')).toHaveLength(1);
        expect(wrapper.find('.root > .default + .override').exists()).toBe(true);
    });

    it('renders the parent layer once per iteration of a v-for', async () => {
        const wrapper = await mountBlock({
            name: 'parent-under-v-for',
            override: '<template v-for="item in items" :key="item"><sw-block-parent /></template>',
            data: { items: [1, 2, 3] },
        });

        expect(wrapper.findAll('.default')).toHaveLength(3);

        await wrapper.setData({ label: 'updated', items: [1, 2] });

        expect(wrapper.findAll('.default').map((node) => node.text())).toEqual(['updated', 'updated']);
    });

    it('renders the parent layer when it mounts after the block rendered', async () => {
        const wrapper = await mountBlock({
            name: 'parent-deferred',
            override: '<lazy-content><sw-block-parent /></lazy-content>',
            components: {
                'lazy-content': defineAsyncComponent(async () => {
                    await nextTick();

                    return { template: '<section class="lazy"><slot /></section>' };
                }),
            },
        });

        await flushPromises();

        expect(wrapper.find('.lazy > .default').text()).toBe('default');

        await wrapper.setData({ label: 'updated' });

        expect(wrapper.find('.lazy > .default').text()).toBe('updated');
    });

    it('renders the layer directly below the layer it is placed in', async () => {
        const wrapper = mount(
            {
                template: `
                    <div>
                        <sw-block extends="parent-chain"><i class="first"><sw-block-parent /></i></sw-block>
                        <sw-block extends="parent-chain"><b class="second"><sw-block-parent /></b></sw-block>
                        <div class="root">
                            <sw-block name="parent-chain"><span class="default" /></sw-block>
                        </div>
                    </div>
                `,
                components: {
                    'sw-block': await wrapTestComponent('sw-block', { sync: true }),
                    'sw-block-parent': await wrapTestComponent('sw-block-parent', { sync: true }),
                },
            },
            {
                global: { plugins: [createDataScopeFixture()] },
            },
        );

        expect(wrapper.find('.root > .second > .first > .default').exists()).toBe(true);
    });
});
