/** @sw-package framework */
import {
    defineComponent,
    getCurrentInstance,
    h,
    provide,
    shallowRef,
    renderList,
    onBeforeUpdate,
    onBeforeUnmount,
    type ComponentInternalInstance,
    type Slot,
    type VNode,
} from 'vue';
import { getLegacyComponentNames } from './component-lineage';
import { parse, NodeTypes } from '@vue/compiler-dom';
import { getBlockEntries, subscribeToBlockIndex } from 'src/core/factory/twig-block-index';
import { createBlockDataScope } from 'src/app/adapter/composition-extension-system/data-scope-helper';
import { createShimSlot, type ShimSlot } from './create-shim-slot';
import parentsInjectionKey from '../sw-block/parents-injection-key';
import reduceToSingleRoot from '../reduce-to-single-root';

type SlotGroup = { name: string; names: string[]; children: string[] };
type SlotMetadata = { scope: Record<PropertyKey, unknown>; groups: SlotGroup[] };
type Slots = Record<string, Slot>;
const indexVersion = shallowRef(0);
subscribeToBlockIndex(() => {
    indexVersion.value += 1;
});
const slotCapture = { render: () => null };

// Render functions retain their lexical scope until the receiver has consumed its slots.
// Keep one render generation per host, and release all condition reservations before replacement.
const hostRenderers = new WeakMap<ComponentInternalInstance, Set<ShimSlot>>();
function retainRenderer(owner: ComponentInternalInstance | null, renderer: ShimSlot): void {
    if (!owner) return;
    let renderers = hostRenderers.get(owner);
    if (!renderers) {
        renderers = new Set();
        hostRenderers.set(owner, renderers);
        const ownedRenderers = renderers;
        const dispose = () => {
            ownedRenderers.forEach((slot) => slot.dispose());
            ownedRenderers.clear();
        };
        onBeforeUpdate(dispose, owner);
        onBeforeUnmount(dispose, owner);
    }
    renderers.add(renderer);
}

const SlotLayer = defineComponent({
    name: 'LegacySlotLayer',
    props: {
        content: { type: Function as PropType<Slot>, required: true },
        parent: { type: Function as PropType<Slot>, required: true },
    },
    setup(props) {
        provide(parentsInjectionKey, (...args: unknown[]) => props.parent(...args));
        return () => reduceToSingleRoot(props.content());
    },
});

/** @private */
export const mapSlotNames = renderList;

function extractSlotTemplate(template: string): { template: string; includesParent: boolean } {
    const root = parse(template);
    const parents = root.children.filter((node) => node.type === NodeTypes.ELEMENT && node.tag === 'sw-block-parent');
    let content = template;
    for (const parent of [...parents].reverse()) {
        content = content.slice(0, parent.loc.start.offset) + content.slice(parent.loc.end.offset);
    }
    return { template: content, includesParent: parents.length > 0 };
}

function extendSlot(content: Slot, parent: Slot | undefined): Slot {
    return (...props: unknown[]) => [
        h(SlotLayer, {
            content: () => content(...props),
            parent: () => parent?.(...props) ?? [],
        }),
    ];
}

/**
 * Merge structural Twig blocks into the receiver's slots before Vue creates or updates the component.
 * Top-level parent tags retain predecessor descriptors; parent tags inside a slot render that slot's predecessor.
 * @private
 */
export function applyLegacySlotBlocks(vnode: VNode): VNode {
    void indexVersion.value;
    const metadata = vnode.props?.__swSlotBlocks as SlotMetadata | undefined;
    if (!metadata) return vnode;
    vnode.props = { ...vnode.props };
    delete vnode.props.__swSlotBlocks;
    const owner = getCurrentInstance();
    const names = getLegacyComponentNames(owner);
    const slots: Slots = { ...(vnode.children as Slots) };
    const contributions = new Map<string, string[]>();
    for (const group of metadata.groups) {
        const ownedNames = new Set([
            ...group.names,
            ...group.children.flatMap((name) => contributions.get(name) ?? []),
        ]);
        let previous: Slots = Object.fromEntries(
            [...ownedNames]
                .filter((name) => typeof slots[name] === 'function')
                .map((name) => [
                    name,
                    slots[name],
                ]),
        );
        for (const entry of getBlockEntries(group.name, names)) {
            const extracted = extractSlotTemplate(entry.innerTemplate);
            const render = createShimSlot(
                { ...entry, innerTemplate: `<component :is="$swSlotCapture">${extracted.template}</component>` },
                group.name,
            );
            retainRenderer(owner, render);
            const scope = createBlockDataScope(metadata.scope, { $swSlotCapture: slotCapture });
            const rendered = render(scope);
            const descriptors = rendered[0]?.children as Slots | null;
            const next: Slots = extracted.includesParent ? { ...previous } : {};
            for (const [
                name,
                content,
            ] of Object.entries(descriptors ?? {})) {
                if (typeof content === 'function') next[name] = extendSlot(content, previous[name]);
            }
            for (const name of ownedNames) delete slots[name];
            Object.assign(slots, next);
            Object.keys(next).forEach((name) => ownedNames.add(name));
            previous = next;
        }
        contributions.set(group.name, [...ownedNames]);
    }
    vnode.children = { ...slots, _: 2 };
    vnode.patchFlag |= 1024; // Vue's DYNAMIC_SLOTS flag: descriptor changes must update an otherwise stable receiver.
    const compiledVNode = vnode as VNode & { dynamicProps?: string[] | null };
    compiledVNode.dynamicProps = compiledVNode.dynamicProps?.filter((name) => name !== '__swSlotBlocks') ?? null;
    return vnode;
}
