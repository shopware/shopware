const { deepCopyObject } = Shopware.Utils.object;

type variantKeys = 'neutral' | 'progress' | 'done' | 'warning' | 'danger';

type style = {
    icon: variantKeys;
    color: variantKeys;
    variant: variantKeys;
}

type storedStyle = {
    selectBackgroundStyle: string;
    iconBackgroundStyle: string;
    icon: string;
    variant: string;
    colorCode: string;
    iconStyle: string;
}

type store = {
    [key: string]: storedStyle | {
        [key: string]: storedStyle
    },
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type stateStyleService = {
    getPlaceholder: () => storedStyle;
    getStyle: (stateMachine: string, state: string) => (style);
    addStyle: (stateMachine: string, state: string, style: style) => void
};

/**
 * @package admin
 *
 * @memberOf module:core/service/login
 * @constructor
 * @method createStateStyleService
 * @returns {Object}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class StateStyleService {
    $store: store = {
        placeholder: {
            icon: 'regular-chevron-down-xxs',
            iconStyle: 'sw-order-state__bg-neutral-icon',
            iconBackgroundStyle: 'sw-order-state__bg-neutral-icon-bg',
            selectBackgroundStyle: 'sw-order-state__bg-neutral-select',
            variant: 'neutral',
            colorCode: '#94a6b8',
        },
    };

    $icons = {
        neutral: 'regular-chevron-down-xxs',
        progress: 'regular-circle-xxs',
        warning: 'regular-exclamation-s',
        done: 'regular-checkmark-xxs',
        danger: 'regular-times-xs',
    };

    $colors = {
        neutral: 'sw-order-state__neutral',
        progress: 'sw-order-state__progress',
        done: 'sw-order-state__success',
        warning: 'sw-order-state__warning',
        danger: 'sw-order-state__danger',
    };

    $colorCodes = {
        neutral: '#94a6b8',
        progress: '#189eff',
        done: '#37d046',
        warning: '#ffab22',
        danger: '#de294c',
    };

    $variants = {
        neutral: 'neutral',
        progress: 'info',
        done: 'success',
        warning: 'warning',
        danger: 'danger',
    };

    getPlaceholder(): storedStyle {
        return this.$store.placeholder as storedStyle;
    }

    addStyle(stateMachine: string, state: string, style: style): void {
        if (!(stateMachine in this.$store)) {
            this.$store[stateMachine] = {};
        }

        const entry = deepCopyObject(this.getPlaceholder());

        if (style.icon in this.$icons) {
            entry.icon = this.$icons[style.icon];
        }

        if (style.color in this.$colors) {
            entry.iconStyle = `${this.$colors[style.color]}-icon`;
            entry.iconBackgroundStyle = `${this.$colors[style.color]}-icon-bg`;
            entry.selectBackgroundStyle = `${this.$colors[style.color]}-select`;
        }

        if (style.color in this.$colorCodes) {
            entry.colorCode = this.$colorCodes[style.color];
        }

        if (style.variant in this.$variants) {
            entry.variant = this.$variants[style.variant];
        }

        // @ts-expect-error
        this.$store[stateMachine][state] = entry;
    }

    getStyle(stateMachine: string, state: string): storedStyle {
        if (stateMachine in this.$store && state in this.$store[stateMachine]) {
            // @ts-expect-error
            return this.$store[stateMachine][state] as storedStyle;
        }

        return this.getPlaceholder();
    }
}
