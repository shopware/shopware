/**
 * @sw-package inventory
 */
import convert, { type Unit } from 'convert-units';

const convertUnit = (value: number, fromUnit: Unit, toUnit: Unit, precision: number = 2): number => {
    const converted = convert(value).from(fromUnit).to(toUnit);

    return Number(converted.toFixed(precision));
};

/**
 * @private
 */
export default {
    convertUnit,
};
