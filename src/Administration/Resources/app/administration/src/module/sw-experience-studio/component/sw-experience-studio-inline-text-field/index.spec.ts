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
        label: `label.${path}`,
        description: '',
        group: 'basic',
        valueType,
        contextType: 'single',
        projection: null,
    }) as ContentSystemMappingCandidate;

const createVm = (value: string, candidates = [candidate('product.name')]) => {
    const vm = {
        value,
        candidates,
        disabled: false,
        editorHtml: '',
        lastEmittedValue: null as string | null,
        isMappingModalOpen: false,
        activeEditor: null as unknown,
        emitted: [] as string[],
        $emit(_event: string, payload: string) {
            this.emitted.push(payload);
        },
        toEditorHtml(next: string) {
            return methods.toEditorHtml.call(this, next);
        },
        onCloseMappingModal() {
            methods.onCloseMappingModal.call(this);
        },
    };

    Object.defineProperty(vm, 'candidatePaths', {
        get: () => computed.candidatePaths.call(vm),
    });

    vm.editorHtml = vm.toEditorHtml(value);

    return vm;
};

describe('module/sw-experience-studio/component/sw-experience-studio-inline-text-field', () => {
    it('shows a known token as a node and an unknown one as text', () => {
        const vm = createVm('<p>{{map:product.name}} and {{map:product.nmae}}</p>');

        expect(vm.editorHtml).toBe('<p><span data-sw-map="product.name"></span> and {{map:product.nmae}}</p>');
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

    it('redraws tokens as nodes once the catalogue arrives, but not once the author has typed', () => {
        const pending = createVm('<p>{{map:product.name}}</p>', []);
        expect(pending.editorHtml).toBe('<p>{{map:product.name}}</p>');

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

        methods.onOpenMappingModal.call(vm, { chain: () => ({ focus: () => ({ insertContent: () => ({ run: () => {} }) }) }) });

        expect(vm.isMappingModalOpen).toBe(false);
    });
});
