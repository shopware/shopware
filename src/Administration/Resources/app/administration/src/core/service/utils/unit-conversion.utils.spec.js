/**
 * @sw-package inventory
 */
import unitConversionUtils from './unit-conversion.utils';

describe('src/core/service/utils/unit-conversion', () => {
    it('should convert length units correctly', () => {
        expect(unitConversionUtils.convertUnit(100, 'mm', 'cm')).toBe(10);
        expect(unitConversionUtils.convertUnit(100, 'cm', 'm')).toBe(1);
        expect(unitConversionUtils.convertUnit(1000, 'm', 'km')).toBe(1);
        expect(unitConversionUtils.convertUnit(25.4, 'mm', 'in')).toBe(1);
        expect(unitConversionUtils.convertUnit(12, 'in', 'ft')).toBe(1);
    });

    it('should convert mass units correctly', () => {
        expect(unitConversionUtils.convertUnit(1, 'kg', 'g')).toBe(1000);
        expect(unitConversionUtils.convertUnit(1, 'g', 'mg')).toBe(1000);
        expect(unitConversionUtils.convertUnit(1, 'kg', 'oz')).toBe(35.27);
        expect(unitConversionUtils.convertUnit(16, 'oz', 'lb')).toBe(1);
    });

    it('should handle zero values', () => {
        expect(unitConversionUtils.convertUnit(0, 'mm', 'cm')).toBe(0);
        expect(unitConversionUtils.convertUnit(0, 'kg', 'g')).toBe(0);
    });

    it('should handle negative values', () => {
        expect(unitConversionUtils.convertUnit(-100, 'mm', 'cm')).toBe(-10);
        expect(unitConversionUtils.convertUnit(-1, 'kg', 'g')).toBe(-1000);
    });
});
