import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent } from 'vue';
import type { PublicInstance } from 'src/core/public-component';
import type SwBulkEditProductVisibility from '../index';
import { useProductDetailStore } from '../dependencies';

/**
 * @sw-package inventory
 */

/**
 * The link under the select that opens the modal with the visibility per sales channel.
 *
 * @private
 */
export const LINK_ADVANCED_VISIBILITY = '.sw-card__quick-link.advanced-visibility';

/**
 * The modal with the visibility per sales channel.
 *
 * @private
 */
export const MODAL_ADVANCED_VISIBILITY = '.sw-product-category-form__visibility_modal';

const wrappers: VueWrapper[] = [];

const InheritWrapperStub = defineComponent({
    name: 'sw-inherit-wrapper',
    props: [
        'value',
        'inheritedValue',
        'customRemoveInheritanceFunction',
        'isAssociation',
    ],
    emits: ['update:value'],
    data() {
        return { forceInheritanceRemove: false };
    },
    template: `<div class="sw-inherit-wrapper">
        <slot name="content" :currentValue="value" :isInherited="false" :updateCurrentValue="(v) => $emit('update:value', v)" />
    </div>`,
});

const VisibilitySelectStub = defineComponent({
    name: 'sw-product-visibility-select',
    props: ['entityCollection', 'placeholder', 'disabled'],
    emits: ['update:entity-collection'],
    template: '<div class="sw-product-visibility-select" />',
});

const ModalStub = defineComponent({
    name: 'sw-modal',
    props: ['title', 'variant'],
    emits: ['modal-close'],
    template: '<div class="sw-modal"><slot /><slot name="modal-footer" /></div>',
});

/**
 * A visibility of the product in the sales channel `salesChannelId`.
 *
 * @private
 */
export function createVisibility(salesChannelId: string, visibility = 30): Record<string, unknown> {
    return {
        productId: 'productId',
        productVersionId: 'productVersionId',
        salesChannelId,
        salesChannel: { id: salesChannelId },
        visibility,
    };
}

/**
 * Puts a product with these visibilities into the product detail store, as the bulk edit does.
 * The visibilities carry their entity name like an entity collection.
 *
 * @private
 */
export function setUpProduct(visibilities: Array<Record<string, unknown>>): void {
    useProductDetailStore().product = {
        id: 'productId',
        visibilities: Object.assign(visibilities, { entity: 'product_visibility' }),
    } as never;
}

/**
 * Returns the visibilities of the product in the store.
 *
 * @private
 */
export function getProductVisibilities(): Array<Record<string, unknown>> {
    return useProductDetailStore().product.visibilities as never;
}

/**
 * Mounts the bulk edit product visibility. A created visibility names the entity of the repository that created it.
 * Its `vm` has the public members only, for the public-api spec.
 *
 * @private
 */
export async function mountProductVisibility(
    props: { disabled?: boolean } = {},
): Promise<VueWrapper<PublicInstance<typeof SwBulkEditProductVisibility>>> {
    const wrapper = mount(await wrapTestComponent('sw-bulk-edit-product-visibility', { sync: true }), {
        global: {
            stubs: {
                'sw-inherit-wrapper': InheritWrapperStub,
                'sw-product-visibility-select': VisibilitySelectStub,
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-modal': ModalStub,
                'sw-product-visibility-detail': true,
                'mt-button': { template: '<button class="mt-button" @click="$emit(\'click\')"><slot /></button>' },
                'mt-icon': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entityName: string) => ({
                        create: () => ({ createdBy: entityName }),
                    }),
                },
            },
        },
        props: { bulkEditProduct: {}, ...props },
    });
    wrappers.push(wrapper);
    await flushPromises();

    return wrapper as never;
}

/**
 * Returns the select of the visibilities.
 *
 * @private
 */
export function getVisibilitySelect(wrapper: VueWrapper) {
    return wrapper.findComponent(VisibilitySelectStub);
}

/**
 * Lets the select change the visibilities, as choosing a sales channel does.
 *
 * @private
 */
export async function selectVisibilities(wrapper: VueWrapper, visibilities: Array<Record<string, unknown>>): Promise<void> {
    getVisibilitySelect(wrapper).vm.$emit('update:entity-collection', visibilities);
    await flushPromises();
}

/**
 * Lets the inherit wrapper remove the inheritance of the visibilities, as its toggle does,
 * and returns what the component hands back as the new value.
 *
 * @private
 */
export async function removeInheritance(wrapper: VueWrapper, inheritedVisibilities: Array<Record<string, unknown>>) {
    const inheritWrapper = wrapper.findComponent(InheritWrapperStub);
    const removeInheritanceFunction = inheritWrapper.props('customRemoveInheritanceFunction') as (
        newValue: Array<Record<string, unknown>>,
    ) => unknown;
    const result = removeInheritanceFunction(inheritedVisibilities);
    await flushPromises();

    return {
        result,
        forceInheritanceRemove: inheritWrapper.vm.forceInheritanceRemove,
    };
}

/**
 * Clicks the link that opens the modal with the visibility per sales channel.
 *
 * @private
 */
export async function openAdvancedVisibility(wrapper: VueWrapper): Promise<void> {
    await wrapper.find(LINK_ADVANCED_VISIBILITY).trigger('click');
    await flushPromises();
}

/**
 * Clicks the apply button of the open modal.
 *
 * @private
 */
export async function applyAdvancedVisibility(wrapper: VueWrapper): Promise<void> {
    await wrapper.find(`${MODAL_ADVANCED_VISIBILITY} .mt-button`).trigger('click');
    await flushPromises();
}

/**
 * Lets the open modal close itself, as its close button or the escape key does.
 *
 * @private
 */
export async function closeAdvancedVisibility(wrapper: VueWrapper): Promise<void> {
    wrapper.findComponent(ModalStub).vm.$emit('modal-close');
    await flushPromises();
}

/**
 * Unmounts every component the tests mounted.
 *
 * @private
 */
export function unmountProductVisibility(): void {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
}
