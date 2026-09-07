/**
 * @sw-package framework
 * @group disabledCompat
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import createDataScopeFixture from '../sw-block-override.spec/test-utils/create-data-scope-fixture';
import '../../../../store/block-override.store';

/**
 * A component the SFC migration produced: its whole template is one `<sw-block>`. Everything here
 * asserts that such a component behaves like the single-rooted component it was before the block
 * conversion — the caller's attributes reach the root element and `$el` is that element.
 */
async function mountConverted({
    blockContent = '<div class="inner">content</div>',
    callerAttributes = '',
    overrides = '',
    hostData = {},
    componentData = {},
}: {
    blockContent?: string;
    callerAttributes?: string;
    overrides?: string;
    hostData?: Record<string, unknown>;
    componentData?: Record<string, unknown>;
} = {}) {
    const swBlock = await wrapTestComponent('sw-block', { sync: true });
    const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });

    return mount(
        {
            template: `
                <div class="host">
                    <converted-component ${callerAttributes} />
                    ${overrides}
                </div>
            `,
            components: {
                'sw-block': swBlock,
                'sw-block-parent': swBlockParent,
                'converted-component': {
                    name: 'converted-component',
                    components: { 'sw-block': swBlock },
                    template: `<sw-block name="converted-block">${blockContent}</sw-block>`,
                    data() {
                        return { ...componentData };
                    },
                },
            },
            data() {
                return { ...hostData };
            },
        },
        {
            global: {
                plugins: [createDataScopeFixture()],
            },
        },
    );
}

/** The root node Vue gave the converted component, which is what `$el` and every caller sees. */
function convertedRoot(wrapper: VueWrapper): Node {
    return (wrapper.findComponent({ name: 'converted-component' }).vm as { $el: Node }).$el;
}

// The fragment case below drops the caller's attributes on purpose, which Vue warns about.
global.allowedErrors.push({ method: 'warn', msg: 'Extraneous non-props attributes' });

describe('sw-block single root', () => {
    it('passes attributes the caller sets on to the block content', async () => {
        const wrapper = await mountConverted({
            callerAttributes: 'id="outer" data-caller="yes"',
        });

        const inner = wrapper.get('.inner');

        expect(inner.attributes('id')).toBe('outer');
        expect(inner.attributes('data-caller')).toBe('yes');
    });

    it('merges the caller class with the class the block content already has', async () => {
        const wrapper = await mountConverted({
            callerAttributes: 'class="from-caller"',
        });

        expect(wrapper.get('.inner').classes()).toStrictEqual([
            'inner',
            'from-caller',
        ]);
    });

    it('forwards a listener the caller registers to the block content', async () => {
        const onClick = jest.fn();
        const wrapper = await mountConverted({
            callerAttributes: '@click="onClick"',
            hostData: { onClick },
        });

        await wrapper.get('.inner').trigger('click');

        expect(onClick).toHaveBeenCalledTimes(1);
    });

    it('exposes the block content element as `$el`', async () => {
        const wrapper = await mountConverted();

        expect(convertedRoot(wrapper)).toBeInstanceOf(HTMLElement);
        expect(convertedRoot(wrapper)).toBe(wrapper.get('.inner').element);
    });

    it('stays single rooted when the block content carries an author comment', async () => {
        const wrapper = await mountConverted({
            blockContent: '<!-- a note --><div class="inner">content</div>',
            callerAttributes: 'id="outer"',
        });

        expect(convertedRoot(wrapper)).toBeInstanceOf(HTMLElement);
        expect(wrapper.get('.inner').attributes('id')).toBe('outer');
    });

    it('stays single rooted through nested blocks', async () => {
        const wrapper = await mountConverted({
            blockContent: '<sw-block name="inner-block"><div class="inner">content</div></sw-block>',
            callerAttributes: 'id="outer"',
        });

        expect(wrapper.get('.inner').attributes('id')).toBe('outer');
        expect(convertedRoot(wrapper)).toBe(wrapper.get('.inner').element);
    });

    it('keeps the placeholder of a falsy `v-if` as the only root', async () => {
        const wrapper = await mountConverted({
            blockContent: '<div v-if="visible" class="inner">content</div>',
            componentData: { visible: false },
        });

        expect(wrapper.find('.inner').exists()).toBe(false);
        expect(convertedRoot(wrapper).nodeType).toBe(Node.COMMENT_NODE);

        await wrapper.findComponent({ name: 'converted-component' }).setData({ visible: true });

        expect(convertedRoot(wrapper)).toBeInstanceOf(HTMLElement);
    });

    it('renders a `v-for` as the only block child unchanged', async () => {
        const wrapper = await mountConverted({
            blockContent: '<div v-for="entry in entries" :key="entry" class="inner">{{ entry }}</div>',
            componentData: {
                entries: [
                    'a',
                    'b',
                ],
            },
        });

        expect(wrapper.findAll('.inner')).toHaveLength(2);
    });

    it('passes the caller attributes on to the content of an override', async () => {
        const wrapper = await mountConverted({
            callerAttributes: 'id="outer"',
            overrides: `
                <sw-block extends="converted-block">
                    <div class="overridden">override</div>
                </sw-block>
            `,
        });

        expect(wrapper.find('.inner').exists()).toBe(false);
        expect(wrapper.get('.overridden').attributes('id')).toBe('outer');
    });

    it('keeps the block content of an override that only renders `sw-block-parent`', async () => {
        const wrapper = await mountConverted({
            callerAttributes: 'id="outer"',
            overrides: `
                <sw-block extends="converted-block">
                    <sw-block-parent />
                </sw-block>
            `,
        });

        expect(convertedRoot(wrapper)).toBe(wrapper.get('.inner').element);
        expect(wrapper.get('.inner').attributes('id')).toBe('outer');
    });

    it('reuses the DOM node of `sw-block-parent` content across re-renders', async () => {
        // The parent content used to be rendered through a fresh arrow function on every render,
        // which Vue reads as a different component type and answers with unmount plus remount.
        const wrapper = await mountConverted({
            blockContent: '<div class="inner">{{ label }}</div>',
            componentData: { label: 'initial' },
            overrides: `
                <sw-block extends="converted-block">
                    <sw-block-parent />
                </sw-block>
            `,
        });

        const domNodeBefore = wrapper.get('.inner').element;

        await wrapper.findComponent({ name: 'converted-component' }).setData({ label: 'updated' });

        expect(wrapper.get('.inner').element).toBe(domNodeBefore);
        expect(wrapper.get('.inner').text()).toBe('updated');
    });

    describe('with genuinely multi-root block content', () => {
        it('leaves the fragment alone, because the component was multi rooted before the conversion too', async () => {
            const wrapper = await mountConverted({
                blockContent: '<div class="first"></div><div class="second"></div>',
                callerAttributes: 'id="outer"',
            });

            expect(wrapper.get('.first').attributes('id')).toBeUndefined();
            expect(wrapper.get('.second').attributes('id')).toBeUndefined();
        });
    });
});
