import round from 'lodash/round';

/**
 * This object contains all the unit conversion functions.
 *
 * The type annotation below makes sure that every conversion function has the same signature
 * and will always return a number.
 */
const conversions: {
    [key: string]: {
       [key: string]: (value: number) => number;
    }
} = {
    // weight units
    kg: {
        g: (value) => value * 1000,
        mg: (value) => value * 1_000_000,
        oz: (value) => value * 35.27396,
        lb: (value) => value * 2.204623,
    },
    mg: {
        kg: (value) => value / 1_000_000,
    },
    g: {
        kg: (value) => value / 1000,
    },
    oz: {
        kg: (value) => value / 35.27396,
    },
    lb: {
        kg: (value) => value / 2.204623,
    },
    // dimensions units
    mm: {
        cm: (value) => value / 10,
        m: (value) => value / 1000,
        km: (value) => value / 1_000_000,
        in: (value) => value / 25.4,
        ft: (value) => value / 304.8,
        mi: (value) => value / 1_609_344,
    },
    cm: {
        mm: (value) => value * 10,
    },
    m: {
        mm: (value) => value * 1000,
    },
    km: {
        mm: (value) => value * 1_000_000,
    },
    in: {
        mm: (value) => value * 25.4,
    },
    ft: {
        mm: (value) => value * 304.8,
    },
    mi: {
        mm: (value) => value * 1_609_344,
    },
    // time units
    hr: {
        yr: (value) => value / 8760,
        mth: (value) => value / 730,
        wk: (value) => value / 168,
        d: (value) => value / 24,
        min: (value) => value * 60,
    },
    yr: {
        hr: (value) => value * 8760,
    },
    mth: {
        hr: (value) => value * 730,
    },
    wk: {
        hr: (value) => value * 168,
    },
    d: {
        hr: (value) => value * 24,
    },
    min: {
        hr: (value) => value / 60,
    },
    // volume units
    m3: {
        cm3: (value) => value * 1_000_000,
        mm3: (value) => value * 1_000_000_000,
        in3: (value) => value * 61_023.74409473,
        ft3: (value) => value * 35.31466672149,
    },
    cm3: {
        m3: (value) => value / 1_000_000,
    },
    mm3: {
        m3: (value) => value / 1_000_000_000,
    },
    in3: {
        m3: (value) => value / 61_023.74409473,
    },
    ft3: {
        m3: (value) => value / 35.31466672149,
    },
};

/**
 * This `Options` union type allows us to add type-safety and autocompletion to the
 * `convertUnit` functions' `to` and `from` options values.
 *
 * Remember, it's crucial that this unit type matches the structure of the `conversions` object.
 * Otherwise, someone could try to convert `kg` to `liter`, which is not possible, but the typescript
 * compiler would not complain and therefore unsafe code can be written.
 */
type Options =
    | {
    from: 'kg',
    to: 'g' | 'mg' | 'oz' | 'lb',
}
    | {
    from: 'g' | 'mg' | 'oz' | 'lb',
    to: 'kg',
}
    | {
    from: 'mm',
    to: 'cm' | 'm' | 'km' | 'in' | 'ft' | 'mi',
}
    | {
    from: 'cm' | 'm' | 'km' | 'in' | 'ft' | 'mi',
    to: 'mm',
}
    | {
    from: 'm3',
    to: 'cm3' | 'mm3' | 'in3' | 'ft3',
}
    | {
    from: 'cm3' | 'mm3' | 'in3' | 'ft3',
    to: 'm3',
}
    | {
    from: 'hr',
    to: 'yr'|'mth'|'wk'|'d'|'min',
}
    | {
    from: 'yr'|'mth'|'wk'|'d'|'min',
    to: 'hr',
}

/**
 * @private
 */
export const baseUnits = {
    weight: 'kg',
    dimension: 'mm',
    time: 'hr',
    volume: 'm3',
};

/**
 * This is the actual function that can be used to convert units.
 *
 * @private
 * @example
 * const convertedValue = convertUnit(1, { from: 'kg', to: 'g' }); // return value: 1000
 */
export function convertUnit(value: number, { to, from }: Options, roundPrecision = 10): number {
    return round(conversions[from][to](value), roundPrecision);
}

/**
 * @private
 */
export default convertUnit;
