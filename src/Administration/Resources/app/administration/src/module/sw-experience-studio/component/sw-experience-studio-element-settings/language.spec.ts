import settingsComponent from './index';

const computed = (settingsComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

describe('element settings in a frame language', () => {
    it.each([
        [{ german: 'Hallo', parent: 'Guten Tag', system: 'Hello' }, 'Hallo'],
        [{ parent: 'Guten Tag', system: 'Hello' }, 'Guten Tag'],
        [{ system: 'Hello' }, 'Hello'],
        [{ german: '', system: 'Hello' }, ''],
        [{ german: false, system: true }, false],
        [{ german: 0, system: 2 }, 0],
    ])('reads the selected entry and its fallbacks from %j', (value, expected) => {
        const type = typeof expected === 'string' ? 'string' : typeof expected === 'boolean' ? 'boolean' : 'integer';
        const values = computed.elementPropertyValues.call({
            languageChain: ['german', 'parent', 'system'],
            selectedElement: { properties: { value } },
            selectedElementType: { properties: { value: { type, translatable: true } } },
        });

        expect(values).toEqual({ value: expected });
    });

    it('evaluates field visibility using values in the frame language', () => {
        const fields = computed.elementFields.call({
            languageChain: ['german', 'system'],
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
});
