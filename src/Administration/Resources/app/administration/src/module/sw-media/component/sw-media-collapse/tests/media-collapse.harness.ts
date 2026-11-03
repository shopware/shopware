import { mount, type VueWrapper } from '@vue/test-utils';
import type { PublicInstance } from 'src/core/public-component';
import type SwMediaCollapse from '../index';

/**
 * @sw-package discovery
 */

/**
 * The header of the section, which opens and closes it.
 *
 * @private
 */
export const HEADER = '.sw-collapse__header';

/**
 * The content of the section, rendered only while it is open.
 *
 * @private
 */
export const CONTENT = '.sw-collapse__content';

/**
 * The title in the header.
 *
 * @private
 */
export const TITLE = '.sw-media-collapse__title';

/**
 * The two chevrons in the header; one of them is hidden.
 *
 * @private
 */
export const CHEVRON = '.sw-media-collapse__button';

const wrappers: VueWrapper[] = [];

/**
 * Mounts the media collapse with a title and a content slot, unless the test passes its own.
 * Its `vm` has the public members only.
 *
 * @private
 */
export async function mountMediaCollapse(
    props: { title?: string; expandOnLoading?: boolean } = {},
    slots: Record<string, string> = {},
): Promise<VueWrapper<PublicInstance<typeof SwMediaCollapse>>> {
    const wrapper = mount(await wrapTestComponent('sw-media-collapse', { sync: true }), {
        props: { title: 'Quick actions', ...props },
        slots: { content: '<p class="test-content">Content</p>', ...slots },
    });
    wrappers.push(wrapper);
    await flushPromises();

    return wrapper as never;
}

/**
 * Clicks the header, as a merchant does to open or close the section.
 *
 * @private
 */
export async function clickHeader(wrapper: VueWrapper): Promise<void> {
    await wrapper.find(HEADER).trigger('click');
    await flushPromises();
}

/**
 * Presses Enter on the focused header.
 *
 * @private
 */
export async function pressEnterOnHeader(wrapper: VueWrapper): Promise<void> {
    await wrapper.find(HEADER).trigger('keydown.enter');
    await flushPromises();
}

/**
 * Tells whether the section shows its content.
 *
 * @private
 */
export function isOpen(wrapper: VueWrapper): boolean {
    return wrapper.find(CONTENT).exists();
}

/**
 * Returns the icon names of the chevrons that are not hidden.
 *
 * @private
 */
export function getShownChevrons(wrapper: VueWrapper): string[] {
    return wrapper
        .findAll(CHEVRON)
        .filter((chevron) => !chevron.classes('is--hidden'))
        .map((chevron) => chevron.attributes('data-testid')?.replace('mt-icon__', '') ?? '');
}

/**
 * Unmounts every media collapse the tests mounted.
 *
 * @private
 */
export function unmountMediaCollapse(): void {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
}
