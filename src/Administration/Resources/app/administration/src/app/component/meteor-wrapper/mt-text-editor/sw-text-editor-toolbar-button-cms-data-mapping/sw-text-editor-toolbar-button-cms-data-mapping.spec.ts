import type { Editor } from '@tiptap/vue-3';
import createSwTextEditorToolbarButtonCmsDataMapping from './index';

describe('src/app/component/meteor-wrapper/mt-text-editor/mt-text-editor-toolbar-button-cms-data-mapping', () => {
    it('should return a custom button', () => {
        const getAvailableDataMappings = jest.fn(() => [
            'dataMapping1',
            'dataMapping2',
        ]);
        const button = createSwTextEditorToolbarButtonCmsDataMapping(getAvailableDataMappings);

        expect(button).toEqual({
            icon: 'regular-variables-xs',
            name: 'cms-data-mapping',
            position: 14000,
            label: 'sw-text-editor-toolbar-button-cms-data-mapping.label',
            children: [
                {
                    name: 'dataMapping1',
                    label: 'dataMapping1',
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                    action: expect.any(Function),
                },
                {
                    name: 'dataMapping2',
                    label: 'dataMapping2',
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                    action: expect.any(Function),
                },
            ],
        });
    });

    it('should call getAvailableDataMappings', () => {
        const getAvailableDataMappings = jest.fn(() => [
            'dataMapping1',
            'dataMapping2',
        ]);
        createSwTextEditorToolbarButtonCmsDataMapping(getAvailableDataMappings);

        expect(getAvailableDataMappings).toHaveBeenCalled();
    });

    it('should insert the data mapping into the editor', () => {
        const getAvailableDataMappings = jest.fn(() => [
            'dataMapping1',
            'dataMapping2',
        ]);
        const button = createSwTextEditorToolbarButtonCmsDataMapping(getAvailableDataMappings);

        const editor = {
            commands: {
                insertContent: jest.fn(),
            },
        };

        button.children?.[0].action?.(editor as unknown as Editor);

        expect(editor.commands.insertContent).toHaveBeenCalledWith('{{ dataMapping1 }}');
    });
});
