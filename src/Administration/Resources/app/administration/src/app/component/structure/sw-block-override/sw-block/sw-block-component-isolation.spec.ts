/**
 * @sw-package framework
 * @group disabledCompat
 *
 * Component isolation of `<sw-block>`: a block is identified by `componentName + blockName` via the
 * `sw-internal-component-name` attribute that the Shopware setup transform stamps on every native block.
 * These tests mount the blocks directly and set `sw-internal-component-name` explicitly to stand in for
 * that stamping.
 */
import { mount } from '@vue/test-utils';
import '../../../../store/block-override.store';
import createDataScopeFixture from '../sw-block-override.spec/test-utils/create-data-scope-fixture';

async function createWrapper(template: string) {
    return mount(
        {
            template,
            components: {
                'sw-block': await wrapTestComponent('sw-block', { sync: true }),
                'sw-block-parent': await wrapTestComponent('sw-block-parent', { sync: true }),
            },
        },
        {
            global: {
                plugins: [createDataScopeFixture()],
            },
        },
    );
}

describe('sw-block component isolation', () => {
    it('does not apply an override from a different component', async () => {
        const wrapper = await createWrapper(`
            <div>
                <div class="host-a">
                    <sw-block name="scoped_block" sw-internal-component-name="component-a" :data="$dataScope">
                        <div class="default-a"></div>
                    </sw-block>
                </div>
                <sw-block extends="scoped_block" sw-internal-component-name="component-b" :data="$dataScope">
                    <div class="override-b"></div>
                </sw-block>
            </div>
        `);

        expect(wrapper.find('.host-a > .default-a').exists()).toBe(true);
        expect(wrapper.find('.override-b').exists()).toBe(false);
    });

    it('applies an override registered for the same component', async () => {
        const wrapper = await createWrapper(`
            <div>
                <div class="host-a">
                    <sw-block name="same_scope_block" sw-internal-component-name="component-a" :data="$dataScope">
                        <div class="default-a"></div>
                    </sw-block>
                </div>
                <sw-block extends="same_scope_block" sw-internal-component-name="component-a" :data="$dataScope">
                    <div class="override-a"></div>
                </sw-block>
            </div>
        `);

        expect(wrapper.find('.host-a > .default-a').exists()).toBe(false);
        expect(wrapper.find('.host-a > .override-a').exists()).toBe(true);
    });

    it('isolates same-named blocks in two different components from each other', async () => {
        const wrapper = await createWrapper(`
            <div>
                <div class="host-a">
                    <sw-block name="dup_block" sw-internal-component-name="component-a" :data="$dataScope">
                        <div class="default-a"></div>
                    </sw-block>
                </div>
                <div class="host-b">
                    <sw-block name="dup_block" sw-internal-component-name="component-b" :data="$dataScope">
                        <div class="default-b"></div>
                    </sw-block>
                </div>
                <sw-block extends="dup_block" sw-internal-component-name="component-a" :data="$dataScope">
                    <sw-block-parent />
                    <div class="override-a"></div>
                </sw-block>
            </div>
        `);

        // The override targets component-a only.
        expect(wrapper.find('.host-a > .default-a').exists()).toBe(true);
        expect(wrapper.find('.host-a > .override-a').exists()).toBe(true);

        // component-b keeps its default content and is untouched by the component-a override.
        expect(wrapper.find('.host-b > .default-b').exists()).toBe(true);
        expect(wrapper.find('.host-b > .override-a').exists()).toBe(false);
    });
});
