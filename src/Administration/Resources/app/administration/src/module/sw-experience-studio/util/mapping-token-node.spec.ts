import { nextTick, reactive } from 'vue';
import { createMappingTokenNode, MAPPING_TOKEN_CLASS } from './mapping-token-node';

type RenderableNode = {
    type: string;
    name: string;
    options: { resolveLabel: (path: string) => string };
    config: {
        group: string;
        inline: boolean;
        atom: boolean;
        parseHTML: () => Array<{ tag: string }>;
        renderHTML: (props: { HTMLAttributes: object }) => unknown[];
        addNodeView: () => (props: { node: { attrs: Record<string, unknown> } }) => {
            dom: HTMLElement;
            destroy?: () => void;
        };
        addAttributes: () => {
            path: {
                default: unknown;
                parseHTML: (element: HTMLElement) => string | null;
                renderHTML: (attributes: { path?: string | null }) => Record<string, string>;
            };
        };
    };
};

const createNode = (resolveLabel: (path: string) => string = (path) => path) =>
    createMappingTokenNode({ resolveLabel }) as unknown as RenderableNode;

describe('module/sw-experience-studio/util/mapping-token-node', () => {
    it('uses the mapping token node name', () => {
        const node = createNode();

        expect(node.name).toBe('mappingToken');
    });

    it('is an inline atom, so the caret cannot land inside a path', () => {
        const { config } = createNode();

        expect(config.group).toBe('inline');
        expect(config.inline).toBe(true);
        expect(config.atom).toBe(true);
    });

    it('reads the path off the marker attribute', () => {
        const { config } = createNode();
        const element = document.createElement('span');
        element.setAttribute('data-sw-map', 'product.name');

        expect(config.parseHTML()).toEqual([{ tag: 'span[data-sw-map]' }]);
        expect(config.addAttributes().path.parseHTML(element)).toBe('product.name');
    });

    it('writes the path back as the marker attribute, and omits it when absent', () => {
        const attribute = createNode().config.addAttributes().path;

        expect(attribute.renderHTML({ path: 'product.name' })).toEqual({ 'data-sw-map': 'product.name' });
        expect(attribute.renderHTML({ path: null })).toEqual({});
    });

    /**
     * `mt-text-editor` parses its `modelValue` on mount and locks the editor behind a "review these changes" gate if
     * re-serializing the result differs from the input. So the serialized form must carry nothing beyond the marker
     * attribute — no class and no label — or every mount of a text containing a chip lands in that gate.
     */
    it('serializes to nothing but the marker attribute', () => {
        const node = createNode(() => 'Product name');

        const rendered = node.config.renderHTML.call(node, {
            HTMLAttributes: { 'data-sw-map': 'product.name' },
        });

        expect(rendered).toEqual([
            'span',
            { 'data-sw-map': 'product.name' },
        ]);
    });

    it('draws the chip with the resolved label, separately from what it serializes', () => {
        const node = createNode((path) => (path === 'product.name' ? 'Product name' : path));

        const { dom } = node.config.addNodeView.call(node)({ node: { attrs: { path: 'product.name' } } });

        expect(dom.tagName).toBe('SPAN');
        expect(dom.className).toBe(MAPPING_TOKEN_CLASS);
        expect(dom.getAttribute('data-sw-map')).toBe('product.name');
        expect(dom.textContent).toBe('Product name');
    });

    it('refreshes a reactive label and stops resolving it after the node view is destroyed', async () => {
        const state = reactive({ label: null as string | null });
        const resolveLabel = jest.fn((path: string) => state.label ?? path);
        const node = createNode(resolveLabel);
        const view = node.config.addNodeView.call(node)({ node: { attrs: { path: 'product.name' } } });
        expect(view.dom.textContent).toBe('product.name');

        state.label = 'Product name';
        await nextTick();

        expect(view.dom.textContent).toBe('Product name');
        expect(view.destroy).toBeDefined();
        view.destroy?.();
        resolveLabel.mockClear();
        state.label = 'Updated label';
        await nextTick();

        expect(resolveLabel).not.toHaveBeenCalled();
        expect(view.dom.textContent).toBe('Product name');
    });
});
