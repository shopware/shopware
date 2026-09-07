/**
 * @sw-package framework
 * @group disabledCompat
 *
 * Scoping behaviour of `<sw-block>`: block matching is scoped to `componentName + blockName` via the
 * `sw-internal-component-name` attribute that the Shopware setup transform stamps on every native block.
 * These tests mount the blocks directly and set `sw-internal-component-name` explicitly to stand in for
 * that stamping.
 */
import { mount } from '@vue/test-utils';
import blockOverrideStore from '../../../../store/block-override.store';
import createDataScopeFixture from '../sw-block-override.spec/test-utils/create-data-scope-fixture';

async function createWrapper(template) {
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

describe('sw-block component scoping', () => {
    beforeAll(() => {
        Shopware.Store.register('blockOverride', blockOverrideStore);
    });

    it('does not apply an override from a different component scope', async () => {
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

    it('applies an override registered under the same component scope', async () => {
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

    it('isolates same-named blocks in two different component scopes from each other', async () => {
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

    it('still matches on the block name alone when no component scope is stamped', async () => {
        const wrapper = await createWrapper(`
            <div>
                <div class="host">
                    <sw-block name="unscoped_block" :data="$dataScope">
                        <div class="default"></div>
                    </sw-block>
                </div>
                <sw-block extends="unscoped_block" :data="$dataScope">
                    <div class="override"></div>
                </sw-block>
            </div>
        `);

        expect(wrapper.find('.host > .default').exists()).toBe(false);
        expect(wrapper.find('.host > .override').exists()).toBe(true);
    });
});
