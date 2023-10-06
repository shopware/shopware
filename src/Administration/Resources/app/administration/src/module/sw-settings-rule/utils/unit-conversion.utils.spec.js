import convertUnit from './unit-conversion.utils';

describe('src/module/sw-promotion/utils/unit-conversion.utils.js', () => {
    describe('weights', () => {
        it('should convert 1 kg to 1000 g', () => {
            expect(convertUnit(1, {
                from: 'kg',
                to: 'g',
            })).toBe(1000);
        });

        it('should convert 1 kg to 1_000_000 mg', () => {
            expect(convertUnit(1, {
                from: 'kg',
                to: 'mg',
            })).toBe(1_000_000);
        });

        it('should convert 1 kg to ounces 35.27396 ounces', () => {
            expect(convertUnit(1, {
                from: 'kg',
                to: 'oz',
            })).toBe(35.27396);
        });

        it('should convert 1 kg to pounds 2.204623 pounds', () => {
            expect(convertUnit(1, {
                from: 'kg',
                to: 'lb',
            })).toBe(2.204623);
        });

        it('should convert 1000 g to 1 kg', () => {
            expect(convertUnit(1000, {
                from: 'g',
                to: 'kg',
            })).toBe(1);
        });

        it('should convert 1_000_000 mg to 1 kg', () => {
            expect(convertUnit(1_000_000, {
                from: 'mg',
                to: 'kg',
            })).toBe(1);
        });

        it('should convert 35.27396 ounces to 1 kg', () => {
            expect(convertUnit(35.27396, {
                from: 'oz',
                to: 'kg',
            })).toBe(1);
        });

        it('should convert 2.204623 pounds to 1 kg', () => {
            expect(convertUnit(2.204623, {
                from: 'lb',
                to: 'kg',
            })).toBe(1);
        });
    });

    describe('lengths', () => {
        it('should convert 1 mm to 0.1 cm', () => {
            expect(convertUnit(1, {
                from: 'mm',
                to: 'cm',
            })).toBe(0.1);
        });

        it('should convert 1000 mm to 1 m', () => {
            expect(convertUnit(1000, {
                from: 'mm',
                to: 'm',
            })).toBe(1);
        });

        it('should convert 1000 mm to 0.001 km', () => {
            expect(convertUnit(1000, {
                from: 'mm',
                to: 'km',
            })).toBe(0.001);
        });

        it('should convert 1 m to 1000 mm', () => {
            expect(convertUnit(1, {
                from: 'm',
                to: 'mm',
            })).toBe(1000);
        });

        it('should convert 1000 mm to 0.0006213712 miles', () => {
            expect(convertUnit(1000, {
                from: 'mm',
                to: 'mi',
            })).toBe(0.0006213712);
        });

        it('should convert 1000 mm to 3.280839895 feet', () => {
            expect(convertUnit(1000, {
                from: 'mm',
                to: 'ft',
            })).toBe(3.280839895);
        });

        it('should convert 1000 mm to 39.3700787402 inches', () => {
            expect(convertUnit(1000, {
                from: 'mm',
                to: 'in',
            })).toBe(39.3700787402);
        });

        it('should convert 1 cm to 10 mm', () => {
            expect(convertUnit(1, {
                from: 'cm',
                to: 'mm',
            })).toBe(10);
        });

        it('should convert 0.001 km to 1000 mm', () => {
            expect(convertUnit(0.001, {
                from: 'km',
                to: 'mm',
            })).toBe(1000);
        });

        it('should convert 0.0006213712 miles to 1000 m', () => {
            expect(convertUnit(0.0006213711922373339, {
                from: 'mi',
                to: 'mm',
            })).toBe(1000);
        });

        it('should convert 3.28084 feet to 1000.000032 mm', () => {
            expect(convertUnit(3.28084, {
                from: 'ft',
                to: 'mm',
            })).toBe(1000.000032);
        });

        it('should convert 39.37008 inches to 1000.000032 mm', () => {
            expect(convertUnit(39.37008, {
                from: 'in',
                to: 'mm',
            })).toBe(1000.000032);
        });
    });

    describe('time', () => {
        it('should convert 365 days to 1 year', () => {
            expect(convertUnit(365, {
                from: 'd',
                to: 'yr',
            })).toBe(1);
        });

        it('should convert 1 year to 365 days', () => {
            expect(convertUnit(1, {
                from: 'yr',
                to: 'd',
            })).toBe(365);
        });

        it('should convert 30 days to 1 month', () => {
            expect(convertUnit(30, {
                from: 'd',
                to: 'mth',
            })).toBe(1);
        });

        it('should convert 1 month to 30 days', () => {
            expect(convertUnit(1, {
                from: 'mth',
                to: 'd',
            })).toBe(30);
        });

        it('should convert 7 days to 1 week', () => {
            expect(convertUnit(7, {
                from: 'd',
                to: 'wk',
            })).toBe(1);
        });

        it('should convert 1 week to 7 days', () => {
            expect(convertUnit(1, {
                from: 'wk',
                to: 'd',
            })).toBe(7);
        });

        it('should convert 24 hours to 1 day', () => {
            expect(convertUnit(24, {
                from: 'hr',
                to: 'd',
            })).toBe(1);
        });

        it('should convert 1 day to 24 hours', () => {
            expect(convertUnit(1, {
                from: 'd',
                to: 'hr',
            })).toBe(24);
        });

        it('should convert 1440 minutes to 1 day', () => {
            expect(convertUnit(1440, {
                from: 'min',
                to: 'd',
            })).toBe(1);
        });

        it('should convert 1 day to 1440 minutes', () => {
            expect(convertUnit(1, {
                from: 'd',
                to: 'min',
            })).toBe(1440);
        });
    });

    describe('volume', () => {
        it('should convert 1 m³ to 1_000_000 cm³', () => {
            expect(convertUnit(1, {
                from: 'm3',
                to: 'cm3',
            })).toBe(1_000_000);
        });

        it('should convert 1 m³ to 1_000_000_000 mm³', () => {
            expect(convertUnit(1, {
                from: 'm3',
                to: 'mm3',
            })).toBe(1_000_000_000);
        });

        it('should convert 1 m³ to 61023.74409473 in³', () => {
            expect(convertUnit(1, {
                from: 'm3',
                to: 'in3',
            })).toBe(61023.74409473);
        });

        it('should convert 1 m³ to 35.3146667215 ft³', () => {
            expect(convertUnit(1, {
                from: 'm3',
                to: 'ft3',
            })).toBe(35.3146667215);
        });

        it('should convert 1_000_000 cm³ to 1 m³', () => {
            expect(convertUnit(1_000_000, {
                from: 'cm3',
                to: 'm3',
            })).toBe(1);
        });

        it('should convert 1_000_000_000 mm³ to 1 m³', () => {
            expect(convertUnit(1_000_000_000, {
                from: 'mm3',
                to: 'm3',
            })).toBe(1);
        });

        it('should convert 61023.74409 in³ to 0.9999999999 m³', () => {
            expect(convertUnit(61023.74409, {
                from: 'in3',
                to: 'm3',
            })).toBe(0.9999999999);
        });

        it('should convert 35.31466672149 ft³ to 1 m³', () => {
            expect(convertUnit(35.31466672149, {
                from: 'ft3',
                to: 'm3',
            })).toBe(1);
        });
    });
});
