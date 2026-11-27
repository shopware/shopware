import { createWrapper, layout } from './helpers';

describe('grid editor column labels', () => {
    let wrapper: ReturnType<typeof createWrapper>;

    afterEach(() => wrapper?.unmount());

    it('names each column select and actions button after its column', () => {
        wrapper = createWrapper({ gridLayout: layout('fraction:6 auto|a1 a2') });
        const columns = wrapper.findAll('.sw-experience-studio-grid-editor__column');

        expect(columns.map((column) => column.findAll('select').map((select) => select.attributes('label')))).toEqual([
            ['sw-experience-studio.gridEditor.width 1', 'sw-experience-studio.gridEditor.type 1'],
            ['sw-experience-studio.gridEditor.type 2'],
        ]);
        expect(
            columns.map((column) =>
                column.get('[aria-label^="sw-experience-studio.gridEditor.columnActions"]').attributes('aria-label'),
            ),
        ).toEqual(['sw-experience-studio.gridEditor.columnActions 1', 'sw-experience-studio.gridEditor.columnActions 2']);
    });
});
