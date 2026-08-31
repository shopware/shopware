import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import { getPropertyControlType, isPropertyVisible } from './element-settings.util';

/**
 * @private
 * @sw-package discovery
 */
export type StyleSettingsField = {
    key: string;
    property: ContentSystemElementTypeProperty;
    breakpointAware: boolean;
};

/**
 * @private
 * @sw-package discovery
 */
const STYLE_FIELD_ORDER = [
    'display',
    'align-self',
    'justify-self',
    'col-span',
    'row-span',
] as const;

/**
 * @private
 * @sw-package discovery
 */
export const STYLE_BREAKPOINTS = [
    'xs',
    'sm',
    'md',
    'lg',
    'xl',
    'xxl',
] as const;

/**
 * @private
 * @sw-package discovery
 */
export function isBreakpointMapValue(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * @private
 * @sw-package discovery
 */
export function isViewportSpecificBreakpointMap(
    value: unknown,
    breakpoints: readonly string[] = STYLE_BREAKPOINTS,
): boolean {
    if (!isBreakpointMapValue(value)) {
        return false;
    }

    const definedEntries = breakpoints
        .map((breakpoint) => value[breakpoint])
        .filter((entry) => entry !== undefined && entry !== null);

    if (definedEntries.length === 0) {
        return false;
    }

    if (definedEntries.length < breakpoints.length) {
        return true;
    }

    const firstValue = definedEntries[0];

    return !definedEntries.every((entry) => entry === firstValue);
}

/**
 * @private
 * @sw-package discovery
 */
export function compareStyleFieldKeys(left: string, right: string): number {
    const leftIndex = STYLE_FIELD_ORDER.indexOf(left as (typeof STYLE_FIELD_ORDER)[number]);
    const rightIndex = STYLE_FIELD_ORDER.indexOf(right as (typeof STYLE_FIELD_ORDER)[number]);

    if (leftIndex !== -1 && rightIndex !== -1) {
        return leftIndex - rightIndex;
    }

    if (leftIndex !== -1) {
        return -1;
    }

    if (rightIndex !== -1) {
        return 1;
    }

    return left.localeCompare(right);
}

/**
 * @private
 * @sw-package discovery
 */
export function styleOptionToElementProperty(
    key: string,
    option: ContentSystemStyleOptionSpecification,
): ContentSystemElementTypeProperty {
    const adminUI = option.adminUI ?? {};
    const adminComponent = typeof adminUI.component === 'string' ? adminUI.component : undefined;
    const props = {
        ...(typeof adminUI.props === 'object' && adminUI.props !== null ? adminUI.props : {}),
    };

    if (option.range) {
        if (option.range.min !== undefined) {
            props.min = option.range.min;
        }

        if (option.range.max !== undefined) {
            props.max = option.range.max;
        }
    }

    let mappedComponent = adminComponent;

    if (
        option.breakpointAware &&
        adminComponent !== 'box-spacing' &&
        (option.type === 'integer' || option.type === 'number' || adminComponent === 'number')
    ) {
        mappedComponent = 'responsive-number';
    }

    return {
        type: option.breakpointAware
            ? [
                  option.type,
                  'object',
              ]
            : option.type,
        translatable: false,
        enum: option.enum,
        default: option.default,
        required: false,
        title: typeof adminUI.label === 'string' && adminUI.label.length > 0 ? adminUI.label : key,
        description: typeof adminUI.description === 'string' ? adminUI.description : '',
        adminUI: {
            ...adminUI,
            component: mappedComponent,
            props,
        },
    };
}

/**
 * @private
 * @sw-package discovery
 */
export function getEditableStyleFields(
    styleOptions: Record<string, ContentSystemStyleOptionSpecification>,
    styleValues: Record<string, unknown>,
): StyleSettingsField[] {
    const resolvedValues = Object.entries(styleOptions).reduce<Record<string, unknown>>(
        (
            accumulator,
            [
                key,
                option,
            ],
        ) => {
            const property = styleOptionToElementProperty(key, option);
            const currentValue = styleValues[key];

            accumulator[key] = currentValue !== undefined ? currentValue : property.default;

            return accumulator;
        },
        {},
    );

    return Object.entries(styleOptions)
        .map(
            ([
                key,
                option,
            ]) => ({
                key,
                property: styleOptionToElementProperty(key, option),
                breakpointAware: option.breakpointAware,
            }),
        )
        .filter(({ property }) => getPropertyControlType(property) !== null)
        .filter(({ property }) => isPropertyVisible(property, resolvedValues))
        .sort((left, right) => compareStyleFieldKeys(left.key, right.key));
}
