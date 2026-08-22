import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';
import { getPropertyControlType } from './element-settings.util';
import {
    compareStyleFieldKeys,
    getEditableStyleFields,
    isViewportSpecificBreakpointMap,
    styleOptionToElementProperty,
} from './style-settings.util';

describe('module/sw-experience-studio/util/style-settings.util', () => {
    const colSpanOption: ContentSystemStyleOptionSpecification = {
        type: 'integer',
        enum: null,
        range: { min: 1, max: 12 },
        maxLength: null,
        default: null,
        breakpointAware: true,
        adminUI: {
            component: 'number',
            label: 'Column Span',
            description: 'How many grid columns the element spans.',
            props: {
                min: 1,
                max: 12,
                step: 1,
            },
        },
    };

    const displayOption: ContentSystemStyleOptionSpecification = {
        type: 'boolean',
        enum: null,
        range: null,
        maxLength: null,
        default: true,
        breakpointAware: true,
        adminUI: {
            component: 'switch',
            label: 'Display',
            description: 'Whether the element is rendered at this breakpoint.',
        },
    };

    const paddingOption: ContentSystemStyleOptionSpecification = {
        type: 'string',
        enum: null,
        range: null,
        maxLength: 64,
        default: null,
        breakpointAware: true,
        adminUI: {
            component: 'box-spacing',
            label: 'Padding',
        },
    };

    it('maps style option adminUI labels to property titles', () => {
        const property = styleOptionToElementProperty('col-span', colSpanOption);

        expect(property.title).toBe('Column Span');
        expect(property.description).toBe('How many grid columns the element spans.');
    });

    it('maps breakpoint-aware numeric options to responsive-number controls', () => {
        const property = styleOptionToElementProperty('col-span', colSpanOption);

        expect(property.adminUI?.component).toBe('responsive-number');
        expect(property.adminUI?.props).toEqual({
            min: 1,
            max: 12,
            step: 1,
        });
    });

    it('keeps box-spacing controls on breakpoint-aware string options', () => {
        const property = styleOptionToElementProperty('padding', paddingOption);

        expect(property.adminUI?.component).toBe('box-spacing');
        expect(getPropertyControlType(property)).toBe('box-spacing');
    });

    it('keeps breakpoint-aware non-numeric options on their base control', () => {
        const property = styleOptionToElementProperty('display', displayOption);

        expect(property.adminUI?.component).toBe('switch');
        expect(property.type).toEqual([
            'boolean',
            'object',
        ]);
    });

    it('maps alignment style options to radio panel controls', () => {
        const alignSelfOption: ContentSystemStyleOptionSpecification = {
            type: 'string',
            enum: [
                'auto',
                'start',
                'center',
                'end',
                'stretch',
                'baseline',
            ],
            range: null,
            maxLength: null,
            default: 'auto',
            breakpointAware: true,
            adminUI: {
                component: 'radio-panel',
                label: 'Align Self',
                props: {
                    options: [
                        { value: 'auto', label: 'Auto', icon: 'regular-circle' },
                        { value: 'start', label: 'Start', icon: 'regular-align-left' },
                    ],
                },
            },
        };

        const property = styleOptionToElementProperty('align-self', alignSelfOption);

        expect(property.adminUI?.component).toBe('radio-panel');
        expect(getPropertyControlType(property)).toBe('radio-panel');
    });

    it('returns editable style fields for registered options', () => {
        const fields = getEditableStyleFields(
            {
                'col-span': colSpanOption,
                display: displayOption,
            },
            {},
        );

        expect(fields.map((field) => field.key)).toEqual([
            'display',
            'col-span',
        ]);
        expect(fields[0].breakpointAware).toBe(true);
    });

    it('orders known style fields in the configured layout sequence', () => {
        const marginOption: ContentSystemStyleOptionSpecification = {
            type: 'string',
            enum: null,
            range: null,
            maxLength: 64,
            default: null,
            breakpointAware: true,
            adminUI: {
                component: 'text',
                label: 'Margin',
            },
        };

        const customGapOption: ContentSystemStyleOptionSpecification = {
            type: 'string',
            enum: null,
            range: null,
            maxLength: 64,
            default: null,
            breakpointAware: true,
            adminUI: {
                component: 'text',
                label: 'Brand Gap',
            },
        };

        const fields = getEditableStyleFields(
            {
                'row-span': colSpanOption,
                'col-span': colSpanOption,
                'align-self': marginOption,
                'brand-gap': customGapOption,
                display: displayOption,
                'justify-self': marginOption,
            },
            {},
        );

        expect(fields.map((field) => field.key)).toEqual([
            'display',
            'align-self',
            'justify-self',
            'col-span',
            'row-span',
            'brand-gap',
        ]);
    });

    it('sorts unknown style fields alphabetically after known fields', () => {
        expect(compareStyleFieldKeys('brand-gap', 'z-index')).toBeLessThan(0);
        expect(compareStyleFieldKeys('display', 'align-self')).toBeLessThan(0);
        expect(compareStyleFieldKeys('align-self', 'display')).toBeGreaterThan(0);
    });

    it('detects viewport-specific breakpoint maps', () => {
        expect(isViewportSpecificBreakpointMap({ lg: 6, xl: 8, xxl: 10 })).toBe(true);
        expect(isViewportSpecificBreakpointMap({ lg: 6 })).toBe(true);
        expect(
            isViewportSpecificBreakpointMap({
                xs: 6,
                sm: 6,
                md: 6,
                lg: 6,
                xl: 6,
                xxl: 6,
            }),
        ).toBe(false);
        expect(isViewportSpecificBreakpointMap(6)).toBe(false);
    });
});
