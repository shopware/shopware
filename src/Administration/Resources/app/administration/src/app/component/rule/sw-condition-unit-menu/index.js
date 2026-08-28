/**
 * @sw-package fundamentals@after-sales
 */

import template from './sw-condition-unit-menu.html.twig';
import './sw-condition-unit-menu.scss';
import convertUnit, { baseUnits } from '../../../../module/sw-settings-rule/utils/unit-conversion.utils';

/**
 * @private
 */
export default {
    template,

    emits: [
        'set-default-unit',
        'change-unit',
    ],

    props: {
        type: {
            type: String,
            required: true,
        },
        value: {
            type: [
                Number,
                Date,
            ],
            default: undefined,
            required: false,
        },
        visibleValue: {
            type: [
                Number,
                Date,
            ],
            default: undefined,
            required: false,
        },
    },

    data() {
        return {
            showMenu: false,
            selectedUnit: null,
            hoveringOverMenu: false,
        };
    },

    computed: {
        defaultUnit() {
            const defaultUnit = baseUnits[this.type];
            this.$emit('set-default-unit', defaultUnit);

            return defaultUnit;
        },

        unitSnippet() {
            if (!this.defaultUnit) {
                return this.$t(`global.sw-condition-generic.units.${this.type}`);
            }

            return this.$t(`global.sw-condition-generic.units.short.${this.selectedUnit || this.defaultUnit}`);
        },

        unitOptions() {
            switch (this.type) {
                case 'weight':
                    return [
                        {
                            label: this.$t('global.sw-condition-generic.units.g'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.g'),
                            value: 'g',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.kg'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.kg'),
                            value: 'kg',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.oz'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.oz'),
                            value: 'oz',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.lb'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.lb'),
                            value: 'lb',
                        },
                    ];
                case 'dimension':
                    return [
                        {
                            label: this.$t('global.sw-condition-generic.units.mm'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.mm'),
                            value: 'mm',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.cm'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.cm'),
                            value: 'cm',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.m'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.m'),
                            value: 'm',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.km'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.km'),
                            value: 'km',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.in'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.in'),
                            value: 'in',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.ft'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.ft'),
                            value: 'ft',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.mi'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.mi'),
                            value: 'mi',
                        },
                    ];
                case 'time':
                    return [
                        {
                            label: this.$t('global.sw-condition-generic.units.min'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.min'),
                            value: 'min',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.hr'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.hr'),
                            value: 'hr',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.d'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.d'),
                            value: 'd',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.wk'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.wk'),
                            value: 'wk',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.mth'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.mth'),
                            value: 'mth',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.yr'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.yr'),
                            value: 'yr',
                        },
                    ];
                case 'volume':
                    return [
                        {
                            label: this.$t('global.sw-condition-generic.units.mm3'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.mm3'),
                            value: 'mm3',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.cm3'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.cm3'),
                            value: 'cm3',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.m3'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.m3'),
                            value: 'm3',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.in3'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.in3'),
                            value: 'in3',
                        },
                        {
                            label: this.$t('global.sw-condition-generic.units.ft3'),
                            shortLabel: this.$t('global.sw-condition-generic.units.short.ft3'),
                            value: 'ft3',
                        },
                    ];
                default:
                    return [];
            }
        },
    },

    methods: {
        onUnitChange(unit) {
            const value = this.getConvertedValue(unit);

            this.$emit('change-unit', {
                value,
                unit: unit,
            });

            this.selectedUnit = unit;
            this.showMenu = false;
        },

        getConvertedValue(unit) {
            // convert value to new unit or set to base value if selected unit is the default unit
            const value =
                unit === this.defaultUnit
                    ? this.value
                    : convertUnit(this.value, {
                          from: this.defaultUnit,
                          to: unit,
                      });

            if (Number.isNaN(value)) {
                return this.value;
            }

            return value;
        },

        isSelected(unit) {
            // mark the first unit option as selected if no value is set
            if (!this.selectedUnit && this.defaultUnit === unit) {
                return true;
            }

            return this.selectedUnit === unit;
        },
    },
};
