import settingsComponent from './index';

const computed = (settingsComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

describe('element settings in a frame language', () => {
    it.each([
        [{ german: 'Hallo', parent: 'Guten Tag', system: 'Hello' }, 'Hallo'],
        [{ parent: 'Guten Tag', system: 'Hello' }, ''],
        [{ system: 'Hello' }, ''],
        [{ german: '', system: 'Hello' }, ''],
        [{ german: false, system: true }, false],
        [{ german: 0, system: 2 }, 0],
        [{ system: 2 }, null],
    ])('reads only the selected language entry from %j', (value, expected) => {
        const type = typeof expected === 'string' ? 'string' : typeof expected === 'boolean' ? 'boolean' : 'integer';
        const values = computed.elementPropertyValues.call({
            languageId: 'german',
            selectedElement: { properties: { value } },
            selectedElementType: { properties: { value: { type, translatable: true } } },
        });

        expect(values).toEqual({ value: expected });
    });

    it('evaluates field visibility using values in the frame language', () => {
        const fields = computed.elementFields.call({
            languageId: 'german',
            selectedElement: { properties: { title: { german: 'Hallo', system: 'Hello' } } },
            selectedElementType: {
                properties: {
                    title: { type: 'string', translatable: true },
                    subtitle: { type: 'string', adminUI: { visibleWhen: { field: 'title', equals: 'Hallo' } } },
                },
            },
        });

        expect(fields).toEqual(expect.arrayContaining([expect.objectContaining({ key: 'subtitle' })]));
    });

    it('evaluates field visibility without inheriting another language or the declared default', () => {
        const fields = computed.elementFields.call({
            languageId: 'german',
            selectedElement: { properties: { title: { parent: 'Hallo', system: 'Hello' } } },
            selectedElementType: {
                properties: {
                    title: { type: 'string', translatable: true, default: 'Hallo' },
                    subtitle: { type: 'string', adminUI: { visibleWhen: { field: 'title', equals: 'Hallo' } } },
                },
            },
        });

        expect(fields).toEqual([expect.objectContaining({ key: 'title' })]);
    });
});
