import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import inlineTextFieldComponent from './index';

type ComponentInternals = {
    methods: Record<string, (...args: unknown[]) => unknown>;
    watch: Record<string, (...args: unknown[]) => unknown>;
    computed: Record<string, (...args: unknown[]) => unknown>;
};

const { methods, watch, computed } = inlineTextFieldComponent as unknown as ComponentInternals;

const candidate = (path: string, valueType = 'string'): ContentSystemMappingCandidate =>
    ({
        path,
        source: { type: 'root', id: path.split('.')[0], path: path.split('.').slice(1).join('.') },
        label: `label.${path}`,
        description: '',
        group: 'basic',
        valueType,
        contextType: 'single',
    }) as ContentSystemMappingCandidate;

const createVm = (value: string, candidates = [candidate('product.name')]) => {
    const vm = {
        value,
        candidates,
        disabled: false,
        editorHtml: '',
        lastEmittedValue: null as string | null,
        parseRevision: 0,
        parsedTokens: [] as { path: string; match: string; offset: number; insideMarkup: boolean }[],
        isMappingModalOpen: false,
        activeEditor: null as unknown,
        emitted: [] as string[],
        $emit(_event: string, payload: string) {
            this.emitted.push(payload);
        },
        toEditorHtml(next: string) {
            return methods.toEditorHtml.call(this, next);
        },
        refreshEditorValue(next: string, updateEditorMarkup = true) {
            const tokens: { path: string; match: string; offset: number; insideMarkup: boolean }[] = [];
            const tokenPattern = /\{\{\s*map:([A-Za-z0-9_.:-]+)\s*\}\}/g;
            for (const result of next.matchAll(tokenPattern)) {
                const match = result[0];
                const path = result[1];
                const offset = result.index;

                if (path && offset !== undefined && path.includes('.')) {
                    tokens.push({ path, match, offset, insideMarkup: false });
                }
            }
            this.parsedTokens = tokens.sort((first, second) => first.offset - second.offset);
            if (updateEditorMarkup) {
                this.editorHtml = this.toEditorHtml(next) as string;
            }
        },
        onCloseMappingModal() {
            methods.onCloseMappingModal.call(this);
        },
    };

    Object.defineProperty(vm, 'candidatePaths', {
        get: () => computed.candidatePaths.call(vm),
    });
    Object.defineProperty(vm, 'knownTokenPaths', {
        get: () =>
            new Set([
                ...(computed.candidatePaths.call(vm) as Set<string>),
                ...vm.parsedTokens.map((token) => token.path),
            ]),
    });

    vm.refreshEditorValue(value);

    return vm;
};

describe('module/sw-experience-studio/component/sw-experience-studio-inline-text-field', () => {
    it('shows server-recognized tokens as nodes even when the candidate catalogue has no matching path', () => {
        const vm = createVm('<p>{{map:product.name}} and {{map:product.nmae}}</p>');

        expect(vm.editorHtml).toBe(
            '<p><span data-sw-map="product.name"></span> and <span data-sw-map="product.nmae"></span></p>',
        );
    });

    it('emits the token form when the editor changes', () => {
        const vm = createVm('<p>{{map:product.name}}</p>');

        methods.onUpdateEditorHtml.call(vm, '<p><span data-sw-map="product.name" class="chip">Product name</span>!</p>');

        expect(vm.emitted).toEqual(['<p>{{map:product.name}}!</p>']);
    });

    /**
     * `mt-text-editor` answers any difference between `modelValue` and `editor.getHTML()` with `setContent()`, which
     * resets the caret. The chip renders with a label the token form does not carry, so re-deriving the markup from
     * the emitted tokens would differ every time and move the caret to the start after every keystroke.
     */
    it('keeps the editor markup verbatim so the editor is never reset mid-edit', () => {
        const vm = createVm('<p>{{map:product.name}}</p>');
        const produced = '<p><span data-sw-map="product.name" class="chip">Product name</span>!</p>';

        methods.onUpdateEditorHtml.call(vm, produced);
        expect(vm.editorHtml).toBe(produced);

        watch.value.call(vm, vm.emitted[0]);
        expect(vm.editorHtml).toBe(produced);
    });

    it('reloads the editor when the value changes from elsewhere', () => {
        const vm = createVm('<p>{{map:product.name}}</p>');

        methods.onUpdateEditorHtml.call(vm, '<p>typed</p>');
        watch.value.call(vm, '<p>undone {{map:product.name}}</p>');

        expect(vm.editorHtml).toBe('<p>undone <span data-sw-map="product.name"></span></p>');
        expect(vm.lastEmittedValue).toBeNull();
    });

    it('ignores a parser response for an older value revision', async () => {
        const responses = new Map<number, (response: { revision: number; tokens: [] }) => void>();
        const parseInlineMappingTokens = jest.fn(
            (_text: string, revision: number) =>
                new Promise<{ revision: number; tokens: [] }>((resolve) => {
                    responses.set(revision, resolve);
                }),
        );
        jest.spyOn(Shopware, 'Service').mockReturnValue({ parseInlineMappingTokens } as never);

        const vm = {
            value: 'older text',
            parseRevision: 0,
            parsedTokens: [],
            editorHtml: '',
            candidates: [],
            candidatePaths: new Set<string>(),
            toEditorHtml: (value: string) => `<p>${value}</p>`,
        };

        const olderRequest = methods.refreshEditorValue.call(vm, 'older text') as Promise<void>;
        vm.value = 'newer text';
        const newerRequest = methods.refreshEditorValue.call(vm, 'newer text') as Promise<void>;

        responses.get(2)?.({ revision: 2, tokens: [] });
        await newerRequest;
        responses.get(1)?.({ revision: 1, tokens: [] });
        await olderRequest;

        expect(vm.editorHtml).toBe('<p>newer text</p>');
        jest.restoreAllMocks();
    });

    it('refreshes spans after a local edit without replacing the editor markup', async () => {
        const parsedToken = {
            path: 'customFields.privateLabel',
            match: '{{map:customFields.privateLabel}}',
            offset: 0,
            insideMarkup: false,
        };
        const parseInlineMappingTokens = jest.fn((_text: string, revision: number) =>
            Promise.resolve({ revision, tokens: [parsedToken] }),
        );
        jest.spyOn(Shopware, 'Service').mockReturnValue({ parseInlineMappingTokens } as never);

        const editorHtml = '<p><span data-sw-map="customFields.privateLabel">customFields.privateLabel</span>!</p>';
        const vm = {
            value: '{{map:customFields.privateLabel}}!',
            parseRevision: 0,
            parsedTokens: [parsedToken],
            candidates: [],
            candidatePaths: new Set<string>(),
            editorHtml: '',
            lastEmittedValue: null as string | null,
            emitted: [] as string[],
            $emit(_event: string, payload: string) {
                this.emitted.push(payload);
            },
            refreshEditorValue(value: string, updateEditorMarkup = true) {
                return methods.refreshEditorValue.call(this, value, updateEditorMarkup);
            },
        };
        Object.defineProperty(vm, 'knownTokenPaths', {
            get: () => new Set(vm.parsedTokens.map((token) => token.path)),
        });

        methods.onUpdateEditorHtml.call(vm, editorHtml);
        await flushPromises();

        expect(parseInlineMappingTokens).toHaveBeenCalledWith('<p>{{map:customFields.privateLabel}}!</p>', 1);
        expect(vm.parsedTokens).toEqual([parsedToken]);
        expect(vm.editorHtml).toBe(editorHtml);
        expect(vm.emitted).toEqual(['<p>{{map:customFields.privateLabel}}!</p>']);
        jest.restoreAllMocks();
    });

    it('redraws tokens as nodes once the catalogue arrives, but not once the author has typed', () => {
        const pending = createVm('<p>{{map:product.name}}</p>', []);
        expect(pending.editorHtml).toBe('<p><span data-sw-map="product.name"></span></p>');

        pending.candidates = [candidate('product.name')];
        watch.candidates.call(pending);
        expect(pending.editorHtml).toBe('<p><span data-sw-map="product.name"></span></p>');

        const edited = createVm('<p>{{map:product.name}}</p>', []);
        methods.onUpdateEditorHtml.call(edited, '<p>mid-edit</p>');
        edited.candidates = [candidate('product.name')];
        watch.candidates.call(edited);
        expect(edited.editorHtml).toBe('<p>mid-edit</p>');
    });

    it('inserts the picked candidate as a node, by name', () => {
        const vm = createVm('<p></p>');
        const run = jest.fn();
        const insertContent = jest.fn(() => ({ run }));
        const editor = { chain: () => ({ focus: () => ({ insertContent }) }) };

        methods.onOpenMappingModal.call(vm, editor);
        expect(vm.isMappingModalOpen).toBe(true);

        methods.onSelectMapping.call(vm, candidate('product.name'));

        expect(insertContent).toHaveBeenCalledWith({
            type: 'mappingToken',
            attrs: { path: 'product.name' },
        });
        expect(run).toHaveBeenCalledTimes(1);
        expect(vm.isMappingModalOpen).toBe(false);
        expect(vm.activeEditor).toBeNull();
    });

    it('refuses to open the mapping modal while disabled', () => {
        const vm = createVm('<p></p>');
        vm.disabled = true;

        methods.onOpenMappingModal.call(vm, {
            chain: () => ({ focus: () => ({ insertContent: () => ({ run: () => {} }) }) }),
        });

        expect(vm.isMappingModalOpen).toBe(false);
    });
});
