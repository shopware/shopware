import type { ContentElementNode } from 'src/core/service/content-element.types';
import detailComponent from './index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

function createEditor(elements: ContentElementNode[] = []) {
    const layout = { id: 'layout-1', name: 'Layout', rootSource: 'category', layout: elements };
    return {
        layout,
        activeLayoutId: layout.id,
        allowSave: true,
        openLayouts: [{ id: layout.id, layout, savedState: '', isNew: true, isSaving: false, saveError: null }],
        layoutLoadCriteria: {},
        layoutRepository: {
            save: jest.fn().mockResolvedValue(undefined),
            get: jest.fn().mockResolvedValue(layout),
        },
        serializeLayoutState: methods.serializeLayoutState,
        getLayoutRootSource: (value: typeof layout) => value.rootSource,
        extractApiErrorDetail: methods.extractApiErrorDetail,
        $t: (key: string) => key,
    };
}

describe('workspace layout save errors and reload', () => {
    it('keeps the API error detail on the layout when saving is refused', async () => {
        const editor = createEditor([{ id: 'image', component: 'Sw:Media:Image', properties: {} }]);
        const authored = structuredClone(editor.layout);
        editor.layoutRepository.save.mockRejectedValue({
            response: { data: { errors: [{ detail: 'Required property "media" has no value.' }] } },
        });

        expect(await methods.saveOpenLayout.call(editor, 'layout-1')).toBe(false);

        expect(editor.openLayouts[0].saveError).toBe('Required property "media" has no value.');
        expect(editor.openLayouts[0].isSaving).toBe(false);
        expect(editor.openLayouts[0].isNew).toBe(true);
        expect(editor.layoutRepository.get).not.toHaveBeenCalled();
        expect(editor.layout).toEqual(authored);
    });

    it('uses the generic error when the rejection carries no API detail', async () => {
        const editor = createEditor();
        editor.layoutRepository.save.mockRejectedValue(new Error('Network down'));

        expect(await methods.saveOpenLayout.call(editor, 'layout-1')).toBe(false);

        expect(editor.openLayouts[0].saveError).toBe('sw-experience-studio.detail.messageSaveError');
        expect(editor.openLayouts[0].isSaving).toBe(false);
    });

    it('retains the saved snapshot when the subsequent reload fails', async () => {
        const editor = createEditor();
        editor.layoutRepository.get.mockRejectedValue(new Error('Network down'));

        expect(await methods.saveOpenLayout.call(editor, 'layout-1')).toBe(false);

        expect(editor.layoutRepository.save).toHaveBeenCalledTimes(1);
        expect(editor.openLayouts[0].isNew).toBe(false);
        expect(editor.openLayouts[0].savedState).toBe(
            JSON.stringify({ name: 'Layout', rootSource: 'category', layout: [] }),
        );
        expect(editor.openLayouts[0].isSaving).toBe(false);
        expect(editor.openLayouts[0].saveError).toBe('sw-experience-studio.detail.messageSaveError');
    });

    it('adopts the server-canonical layout after saving without client-side normalization', async () => {
        const authoredElement: ContentElementNode = {
            component: 'Sw:Filter:Panel',
            style: { 'col-span': 6 },
            id: 'element-1',
            properties: { visibleFilterCount: 5 },
        };
        const canonicalElement: ContentElementNode = {
            id: 'element-1',
            component: 'Sw:Filter:Panel',
            properties: { visibleFilterCount: 5, showLayoutSwitch: true },
            style: { 'col-span': { xs: 6, sm: 6, md: 6, lg: 6, xl: 6, xxl: 6 } },
        };
        const editor = createEditor([authoredElement]);
        const reloaded = { ...editor.layout, layout: [canonicalElement] };
        editor.layoutRepository.get.mockResolvedValue(reloaded);

        expect(await methods.saveOpenLayout.call(editor, 'layout-1')).toBe(true);

        expect(editor.layoutRepository.save).toHaveBeenCalledWith(
            expect.objectContaining({ layout: [authoredElement] }),
            Shopware.Context.api,
        );
        expect(editor.layout).toBe(reloaded);
        expect(editor.openLayouts[0].layout).toBe(reloaded);
        expect(editor.layout.layout[0]).toBe(canonicalElement);
        expect(editor.openLayouts[0].savedState).toBe(
            JSON.stringify({ name: reloaded.name, rootSource: reloaded.rootSource, layout: [canonicalElement] }),
        );
    });
});
