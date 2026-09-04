import {
    formatBoxSpacingSideForInput,
    normalizeBoxSpacingCSSValue,
    normalizeBoxSpacingSide,
    normalizeBoxSpacingUnit,
    parseBoxSpacing,
    serializeBoxSpacing,
} from './box-spacing.util';

describe('module/sw-experience-studio/util/box-spacing.util', () => {
    it('parses single-value shorthand into all sides', () => {
        expect(parseBoxSpacing('5%')).toEqual({
            top: '5%',
            right: '5%',
            bottom: '5%',
            left: '5%',
        });
    });

    it('parses two-value shorthand', () => {
        expect(parseBoxSpacing('8px 16px')).toEqual({
            top: '8',
            right: '16',
            bottom: '8',
            left: '16',
        });
    });

    it('parses four-value shorthand', () => {
        expect(parseBoxSpacing('1px 2px 3px 4px')).toEqual({
            top: '1',
            right: '2',
            bottom: '3',
            left: '4',
        });
    });

    it('strips px from parsed values for input display', () => {
        expect(formatBoxSpacingSideForInput('30px')).toBe('30');
        expect(formatBoxSpacingSideForInput('5%')).toBe('5%');
        expect(formatBoxSpacingSideForInput('1.5rem')).toBe('1.5rem');
        expect(parseBoxSpacing('30px 40px 30px 40px')).toEqual({
            top: '30',
            right: '40',
            bottom: '30',
            left: '40',
        });
    });

    it('normalizes empty sides to zero', () => {
        expect(normalizeBoxSpacingSide('')).toBe('0');
        expect(normalizeBoxSpacingSide('  ')).toBe('0');
        expect(normalizeBoxSpacingSide('12px')).toBe('12px');
        expect(normalizeBoxSpacingSide('12')).toBe('12px');
    });

    it('appends px to unitless numbers and preserves explicit units', () => {
        expect(normalizeBoxSpacingUnit('30')).toBe('30px');
        expect(normalizeBoxSpacingUnit('30px')).toBe('30px');
        expect(normalizeBoxSpacingUnit('5%')).toBe('5%');
        expect(normalizeBoxSpacingUnit('1.5rem')).toBe('1.5rem');
        expect(normalizeBoxSpacingUnit('0')).toBe('0');
        expect(normalizeBoxSpacingUnit('auto')).toBe('auto');
        expect(normalizeBoxSpacingUnit('calc(100% - 20px)')).toBe('calc(100% - 20px)');
    });

    it('serializes unitless side values with px on save', () => {
        expect(
            serializeBoxSpacing(
                {
                    top: '30',
                    right: '40',
                    bottom: '30',
                    left: '40',
                },
                { explicit: true },
            ),
        ).toBe('30px 40px 30px 40px');
    });

    it('serializes uniform sides to a single value when shorthand is allowed', () => {
        expect(
            serializeBoxSpacing({
                top: '5%',
                right: '5%',
                bottom: '5%',
                left: '5%',
            }),
        ).toBe('5%');
    });

    it('serializes uniform sides as explicit four-value CSS when requested', () => {
        expect(
            serializeBoxSpacing(
                {
                    top: '30px',
                    right: '30px',
                    bottom: '30px',
                    left: '30px',
                },
                { explicit: true },
            ),
        ).toBe('30px 30px 30px 30px');
    });

    it('serializes vertical and horizontal pairs when linked', () => {
        expect(
            serializeBoxSpacing(
                {
                    top: '8px',
                    right: '16px',
                    bottom: '8px',
                    left: '16px',
                },
                { linked: true },
            ),
        ).toBe('8px 16px');
    });

    it('collapses equivalent four-value shorthand when horizontal sides match and linked', () => {
        expect(serializeBoxSpacing(parseBoxSpacing('12px 8px 4px 8px'), { linked: true })).toBe('12px 8px 4px');
    });

    it('returns empty string when all sides are empty', () => {
        expect(
            serializeBoxSpacing({
                top: '',
                right: '',
                bottom: '',
                left: '',
            }),
        ).toBe('');
    });

    it('serializes unlinked sides as explicit four-value CSS', () => {
        expect(
            serializeBoxSpacing(
                {
                    top: '20px',
                    right: '40px',
                    bottom: '20px',
                    left: '40px',
                },
                { linked: false },
            ),
        ).toBe('20px 40px 20px 40px');
    });

    it('replaces empty sides with zero when serializing', () => {
        expect(
            serializeBoxSpacing(
                {
                    top: '20px',
                    right: '',
                    bottom: '20px',
                    left: '',
                },
                { linked: false },
            ),
        ).toBe('20px 0 20px 0');
    });

    it('round-trips unlinked sides without collapsing to shorthand', () => {
        const sides = {
            top: '5px',
            right: '10px',
            bottom: '10px',
            left: '10px',
        };

        expect(parseBoxSpacing(serializeBoxSpacing(sides, { linked: false }))).toEqual({
            top: '5',
            right: '10',
            bottom: '10',
            left: '10',
        });
    });

    it('parses explicit four-value strings with zero sides', () => {
        expect(parseBoxSpacing('20px 0 20px 0')).toEqual({
            top: '20',
            right: '0',
            bottom: '20',
            left: '0',
        });
    });

    it('keeps linked shorthand optimization when requested', () => {
        expect(
            serializeBoxSpacing(
                {
                    top: '8px',
                    right: '16px',
                    bottom: '8px',
                    left: '16px',
                },
                { linked: true },
            ),
        ).toBe('8px 16px');
    });

    it('normalizes legacy numeric values to explicit four-value CSS strings', () => {
        expect(normalizeBoxSpacingCSSValue(20)).toBe('20px 20px 20px 20px');
        expect(normalizeBoxSpacingCSSValue('30px')).toBe('30px 30px 30px 30px');
        expect(normalizeBoxSpacingCSSValue('30')).toBe('30px 30px 30px 30px');
    });

    it('normalizes asymmetric CSS values to explicit four-value strings', () => {
        expect(normalizeBoxSpacingCSSValue('20px 40px 20px 40px')).toBe('20px 40px 20px 40px');
    });

    it('does not collapse asymmetric values to a single scalar', () => {
        expect(normalizeBoxSpacingCSSValue('20px 40px 20px 40px')).not.toBe('20');
        expect(normalizeBoxSpacingCSSValue('20px 40px 20px 40px')).not.toBe(20);
    });
});
