/**
 * @sw-package framework
 */
import { nextTick, watchEffect } from 'vue';
import useBlockContext from './use-block-context';

function createLayer(overrides = {}) {
    return { render: () => [], ...overrides };
}

function hostOf(...lineage) {
    const type = lineage.reduce((parent, name) => ({ name, extends: parent }), undefined);

    return { type };
}

describe('use-block-context', () => {
    const { addBlockLayer, removeBlockLayer, getBlockLayers } = useBlockContext();
    let registered = [];

    function add(blockName, layer) {
        addBlockLayer(blockName, layer);
        registered.push([blockName, layer]);

        return layer;
    }

    afterEach(() => {
        registered.forEach(([blockName, layer]) => removeBlockLayer(blockName, layer));
        registered = [];
    });

    it('returns no layers for an unknown block', () => {
        expect(getBlockLayers('unknown')).toEqual([]);
    });

    it('returns layers in registration order', () => {
        const first = add('block', createLayer());
        const second = add('block', createLayer());

        expect(getBlockLayers('block')).toEqual([first, second]);
        expect(getBlockLayers('other')).toEqual([]);
    });

    it('keeps the position of a layer that is added again', () => {
        const first = add('block', createLayer());
        const second = add('block', createLayer());

        addBlockLayer('block', first);

        expect(getBlockLayers('block')).toEqual([first, second]);
    });

    it('removes exactly the given layer', () => {
        const first = add('block', createLayer());
        const second = add('block', createLayer());

        removeBlockLayer('block', first);

        expect(getBlockLayers('block')).toEqual([second]);
    });

    it('orders by priority before registration', () => {
        const late = add('block', createLayer({ priority: 5 }));
        const early = add('block', createLayer({ priority: -1 }));
        const unprioritised = add('block', createLayer());

        expect(getBlockLayers('block')).toEqual([early, unprioritised, late]);
    });

    it('orders layers of a component after the layers of the component it extends', () => {
        const childExtend = add('block', createLayer({ componentName: 'sw-child', scoped: true }));
        const childOverride = add('block', createLayer({ componentName: 'sw-child' }));
        const parentOverride = add('block', createLayer({ componentName: 'sw-parent' }));
        const native = add('block', createLayer());

        expect(getBlockLayers('block', hostOf('sw-parent', 'sw-child'))).toEqual([
            parentOverride,
            native,
            childExtend,
            childOverride,
        ]);
    });

    it('applies scoped layers only to the component and its children', () => {
        const scoped = add('block', createLayer({ componentName: 'sw-child', scoped: true }));

        expect(getBlockLayers('block', hostOf('sw-parent'))).toEqual([]);
        expect(getBlockLayers('block', hostOf('sw-parent', 'sw-child'))).toEqual([scoped]);
        expect(getBlockLayers('block', hostOf('sw-parent', 'sw-child', 'sw-grandchild'))).toEqual([scoped]);
    });

    it('re-runs effects that read a block when a layer of that block is added or re-added', async () => {
        const seen = [];
        const layer = createLayer();

        watchEffect(() => {
            seen.push(getBlockLayers('reactive-block').length);
        });
        add('reactive-block', layer);
        await nextTick();
        addBlockLayer('reactive-block', layer);
        await nextTick();

        expect(seen).toEqual([0, 1, 1]);
    });

    it('does not re-run effects that read a different block', async () => {
        let runs = 0;

        watchEffect(() => {
            getBlockLayers('unrelated-block');
            runs += 1;
        });
        add('reactive-block', createLayer());
        await nextTick();

        expect(runs).toBe(1);
    });
});
