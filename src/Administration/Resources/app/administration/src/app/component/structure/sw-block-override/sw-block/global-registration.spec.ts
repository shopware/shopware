/**
 * @sw-package framework
 */
import { mount, shallowMount } from '@vue/test-utils';
import { unresolvedComponentWarning } from 'test/_helper_/allowedErrors';

/**
 * A component whose template declares a block, registering nothing itself - the point of these
 * tests is what the global Jest setup provides.
 */
const componentWithBlock = {
    name: 'block-consumer',
    template: `
        <div class="block-consumer">
            <sw-block name="block_consumer_content">
                <p class="block-consumer__content">Content</p>
            </sw-block>
        </div>
    `,
};

describe('sw-block global registration', () => {
    it.each([
        [
            'mount',
            mount,
        ],
        [
            'shallowMount',
            shallowMount,
        ],
    ])('renders no element of its own under %s', (_name, mountingMethod) => {
        const wrapper = mountingMethod(componentWithBlock);

        expect(wrapper.html()).not.toContain('<sw-block');
        expect(wrapper.find('.block-consumer__content').exists()).toBe(true);
    });

    it('keeps the block content a direct child, so DOM walking still works', () => {
        const wrapper = mount({
            name: 'block-siblings',
            template: `
                <div class="block-siblings">
                    <sw-block name="block_siblings_first">
                        <p class="block-siblings__item">First</p>
                    </sw-block>
                    <sw-block name="block_siblings_second">
                        <p class="block-siblings__item">Second</p>
                    </sw-block>
                </div>
            `,
        });

        const first = wrapper.find('.block-siblings__item').element;

        expect(first.parentElement?.className).toBe('block-siblings');
        expect(first.nextElementSibling?.textContent).toBe('Second');
    });

    it('does not silence a warning about an unresolved sw-block', () => {
        const isSilenced = (component: string) =>
            unresolvedComponentWarning.msgCheck(`[Vue warn]: Failed to resolve component: ${component}`);

        // Vue logs the warning through console.warn, so an entry on any other channel would silence
        // nothing and let the regression back in unnoticed.
        expect(unresolvedComponentWarning.method).toBe('warn');
        expect(isSilenced('sw-block')).toBe(false);
        expect(isSilenced('sw-block-parent')).toBe(false);
        // Unrelated components stay silenced, so no existing spec changes behaviour.
        expect(isSilenced('sw-block-field')).toBe(true);
        expect(isSilenced('sw-some-other-component')).toBe(true);
    });
});
