import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent } from 'vue';
import type { PublicInstance } from 'src/core/public-component';
import type SwSettingsLoggingEntryInfo from '../index';
import { featureService } from '../dependencies';

/**
 * @sw-package framework
 */

/**
 * The text area that shows the context of the log entry.
 *
 * @private
 */
export const RAW_CONTENT = '.mt-textarea';

/**
 * The tab bar of the 6.8 layout, which a test lets choose a tab as the real one does.
 *
 * @private
 */
export const MT_TABS = 'mt-tabs';

/**
 * The tab bar of the layout before 6.8.
 *
 * @private
 */
export const SW_TABS = '.sw-tabs';

/**
 * The button in the modal footer that closes the modal.
 *
 * @private
 */
export const BUTTON_CLOSE = '.mt-button';

const wrappers: VueWrapper[] = [];

const MeteorTabsStub = defineComponent({
    name: MT_TABS,
    props: [
        'positionIdentifier',
        'defaultItem',
        'items',
        'useRoutesForExtensions',
        'small',
    ],
    emits: ['new-item-active'],
    template: '<div class="mt-tabs" />',
});

/**
 * Mounts the log entry info for this log entry. `majorFeature` turns on the 6.8 layout with meteor tabs.
 * The modal and the tabs are stubs; the modal stub has a button `.sw-modal__close` that closes it like its X.
 * Its `vm` has the public members only, for the public-api spec.
 *
 * @private
 */
export async function mountEntryInfo(
    logEntry: Record<string, unknown>,
    { majorFeature = false }: { majorFeature?: boolean } = {},
): Promise<VueWrapper<PublicInstance<typeof SwSettingsLoggingEntryInfo>>> {
    const wrapper = mount(await wrapTestComponent('sw-settings-logging-entry-info', { sync: true }), {
        props: { logEntry },
        global: {
            stubs: {
                'sw-modal': {
                    props: ['title'],
                    emits: ['modal-close'],
                    template: `<div class="sw-modal">
                        <button class="sw-modal__close" @click="$emit('modal-close')" />
                        <slot />
                        <slot name="modal-footer" />
                    </div>`,
                },
                'sw-tabs': {
                    props: ['positionIdentifier'],
                    template: '<div class="sw-tabs"><slot /><slot name="content" /></div>',
                },
                'sw-tabs-item': {
                    props: ['active'],
                    template: '<button class="sw-tabs-item" :data-active="active"><slot /></button>',
                },
                'mt-tabs': MeteorTabsStub,
                'mt-textarea': {
                    props: ['modelValue'],
                    template: '<textarea class="mt-textarea" :value="modelValue" />',
                },
                'mt-button': {
                    template: '<button class="mt-button"><slot /></button>',
                },
            },
            provide: {
                [featureService]: {
                    isActive: (flag: string) => flag === 'v6.8.0.0' && majorFeature,
                },
            },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();

    return wrapper as never;
}

/**
 * Returns the text the raw tab shows, or `null` when no raw content is shown.
 *
 * @private
 */
export function getShownRawContent(wrapper: VueWrapper): string | null {
    const textarea = wrapper.find(RAW_CONTENT);

    return textarea.exists() ? (textarea.element as HTMLTextAreaElement).value : null;
}

/**
 * Lets the meteor tab bar choose the tab with this name, as a click on it does.
 *
 * @private
 */
export async function chooseTab(wrapper: VueWrapper, name: string): Promise<void> {
    wrapper.getComponent(MeteorTabsStub).vm.$emit('new-item-active', name);
    await flushPromises();
}

/**
 * Returns the props the meteor tab bar gets.
 *
 * @private
 */
export function getMeteorTabsProps(wrapper: VueWrapper): Record<string, unknown> {
    return wrapper.getComponent(MeteorTabsStub).props();
}

/**
 * Clicks the close button in the modal footer.
 *
 * @private
 */
export async function clickCloseButton(wrapper: VueWrapper): Promise<void> {
    await wrapper.find(BUTTON_CLOSE).trigger('click');
}

/**
 * Closes the modal itself, as its X or the Escape key does.
 *
 * @private
 */
export async function closeModal(wrapper: VueWrapper): Promise<void> {
    await wrapper.find('.sw-modal__close').trigger('click');
}

/**
 * Unmounts every log entry info the tests mounted.
 *
 * @private
 */
export function unmountEntryInfos(): void {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
}
