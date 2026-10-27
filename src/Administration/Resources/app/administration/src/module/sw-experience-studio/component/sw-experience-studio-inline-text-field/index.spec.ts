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
        compatibleTypes: [valueType],
        inlineMappable: [
            'string',
            'integer',
            'number',
            'boolean',
        ].includes(valueType),
        canonicalToken: `{{map:${path}}}`,
        contextType: 'single',
    }) as ContentSystemMappingCandidate;

const createVm = (value: string, candidates = [candidate('product.name')]) => {
    const vm = {
        value,
        candidates,
        disabled: false,
        isEditorReady: true,
        editorHtml: '',
        lastEmittedValue: null as string | null,
        parseRevision: 0,
        parseTimeoutId: null as number | null,
        parsedTokens: [] as {
            path: string;
            match: string;
            canonicalToken: string;
            offset: number;
            insideMarkup: boolean;
            malformed: boolean;
        }[],
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
            const tokens: {
                path: string;
                match: string;
                canonicalToken: string;
                offset: number;
                insideMarkup: boolean;
                malformed: boolean;
            }[] = [];
            const tokenPattern = /\{\{\s*map:([A-Za-z0-9_.:-]+)\s*\}\}/g;
            for (const result of next.matchAll(tokenPattern)) {
                const match = result[0];
                const path = result[1];
                const offset = result.index;

                if (path && offset !== undefined && path.includes('.')) {
                    tokens.push({
                        path,
                        match,
                        canonicalToken: `{{map:${path}}}`,
                        offset,
                        insideMarkup: false,
                        malformed: false,
                    });
                }
            }
            this.parsedTokens = tokens.sort((first, second) => first.offset - second.offset);
            if (updateEditorMarkup) {
                this.editorHtml = this.toEditorHtml(next) as string;
            }
        },
        scheduleEditorValueParse(next: string) {
            methods.scheduleEditorValueParse.call(this, next);
        },
        onCloseMappingModal() {
            methods.onCloseMappingModal.call(this);
        },
    };

    Object.defineProperty(vm, 'knownTokenTexts', {
        get: () => computed.knownTokenTexts.call(vm),
    });
    Object.defineProperty(vm, 'knownTokenPaths', {
        get: () => computed.knownTokenPaths.call(vm),
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

    it('does not recognize a malformed server token as a chip or a canonical token', () => {
        const vm = createVm('{{map:product}}', []);
        vm.parsedTokens = [
            {
                path: 'product',
                match: '{{map:product}}',
                canonicalToken: '{{map:product}}',
                offset: 0,
                insideMarkup: false,
                malformed: true,
            },
        ];

        expect((computed.knownTokenTexts.call(vm) as Map<string, string>).has('product')).toBe(false);
        expect(vm.toEditorHtml(vm.value)).toBe('<p>{{map:product}}</p>');
    });

    it('keeps a pathless catalogue source as literal text when it has no canonical token', () => {
        const pathlessCandidate: ContentSystemMappingCandidate = {
            ...candidate('product.name'),
            path: 'context:example',
            source: { type: 'context', id: 'example' },
            inlineMappable: false,
            canonicalToken: null,
        };
        const vm = createVm('{{map:context:example}}', [pathlessCandidate]);

        expect((computed.knownTokenTexts.call(vm) as Map<string, string>).has(pathlessCandidate.path)).toBe(false);
        expect(vm.editorHtml).toBe('<p>{{map:context:example}}</p>');
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
            knownTokenTexts: new Map<string, string>(),
            toEditorHtml: (value: string) => `<p>${value}</p>`,
            onCloseMappingModal: jest.fn(),
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
            canonicalToken: '{{map:customFields.privateLabel}}',
            offset: 0,
            insideMarkup: false,
            malformed: false,
        };
        const parseInlineMappingTokens = jest.fn((_text: string, revision: number) =>
            Promise.resolve({ revision, tokens: [parsedToken] }),
        );
        jest.spyOn(Shopware, 'Service').mockReturnValue({ parseInlineMappingTokens } as never);

        const editorHtml = '<p><span data-sw-map="customFields.privateLabel">customFields.privateLabel</span>!</p>';
        const vm = {
            value: '{{map:customFields.privateLabel}}!',
            parsedTokens: [parsedToken],
            parseRevision: 0,
            parseTimeoutId: null as number | null,
            candidates: [],
            knownTokenTexts: new Map([
                [
                    parsedToken.path,
                    parsedToken.canonicalToken,
                ],
            ]),
            editorHtml: '',
            lastEmittedValue: null as string | null,
            isEditorReady: true,
            emitted: [] as string[],
            $emit(_event: string, payload: string) {
                this.emitted.push(payload);
            },
            refreshEditorValue(value: string, updateEditorMarkup = true, requestRevision?: number) {
                return methods.refreshEditorValue.call(this, value, updateEditorMarkup, requestRevision);
            },
            scheduleEditorValueParse(value: string) {
                methods.scheduleEditorValueParse.call(this, value);
            },
        };
        Object.defineProperty(vm, 'knownTokenPaths', {
            get: () =>
                new Map(
                    vm.parsedTokens.map((token) => [
                        token.path,
                        token.canonicalToken,
                    ]),
                ),
        });

        jest.useFakeTimers();
        methods.onUpdateEditorHtml.call(vm, editorHtml);

        expect(parseInlineMappingTokens).not.toHaveBeenCalled();
        jest.advanceTimersByTime(800);
        await flushPromises();

        expect(parseInlineMappingTokens).toHaveBeenCalledWith('<p>{{map:customFields.privateLabel}}!</p>', 1);
        expect(vm.parsedTokens).toEqual([parsedToken]);
        expect(vm.editorHtml).toBe(editorHtml);
        expect(vm.emitted).toEqual(['<p>{{map:customFields.privateLabel}}!</p>']);
        jest.useRealTimers();
        jest.restoreAllMocks();
    });

    it('parses only the last local edit after the debounce window', async () => {
        jest.useFakeTimers();
        const parseInlineMappingTokens = jest.fn((_text: string, revision: number) =>
            Promise.resolve({ revision, tokens: [] }),
        );
        jest.spyOn(Shopware, 'Service').mockReturnValue({ parseInlineMappingTokens } as never);
        const vm = {
            parseRevision: 0,
            parseTimeoutId: null as number | null,
            refreshEditorValue(value: string, updateEditorMarkup = true, requestRevision?: number) {
                return methods.refreshEditorValue.call(this, value, updateEditorMarkup, requestRevision);
            },
        };

        methods.scheduleEditorValueParse.call(vm, 'first');
        jest.advanceTimersByTime(200);
        methods.scheduleEditorValueParse.call(vm, 'last');
        jest.advanceTimersByTime(799);
        expect(parseInlineMappingTokens).not.toHaveBeenCalled();

        jest.advanceTimersByTime(1);
        await flushPromises();

        expect(parseInlineMappingTokens).toHaveBeenCalledTimes(1);
        expect(parseInlineMappingTokens).toHaveBeenCalledWith('last', 2);
        jest.useRealTimers();
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

    it.each([
        { disabled: true, isEditorReady: true },
        { disabled: false, isEditorReady: false },
    ])('rejects edits and mapping insertion while disabled=$disabled and ready=$isEditorReady', (state) => {
        const vm = createVm('<p>Original text</p>');
        Object.assign(vm, state);
        const originalHtml = vm.editorHtml;
        const insertContent = jest.fn(() => ({ run: jest.fn() }));
        const editor = { chain: () => ({ focus: () => ({ insertContent }) }) };

        methods.onUpdateEditorHtml.call(vm, '<p>Stale update</p>');
        methods.onOpenMappingModal.call(vm, editor);
        vm.activeEditor = editor;
        methods.onSelectMapping.call(vm, candidate('product.name'));

        expect(vm.editorHtml).toBe(originalHtml);
        expect(vm.emitted).toEqual([]);
        expect(vm.isMappingModalOpen).toBe(false);
        expect(insertContent).not.toHaveBeenCalled();
    });
});
