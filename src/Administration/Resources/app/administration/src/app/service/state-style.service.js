const { deepCopyObject } = Shopware.Utils.object;

/**
 *
 * @memberOf module:core/service/login
 * @constructor
 * @method createStateStyleService
 * @returns {Object}
 */
export default function createStateStyleService() {
    const $store = {
        placeholder: {
            icon: 'small-arrow-small-down',
            iconStyle: 'sw-order-state__bg-neutral-icon',
            iconBackgroundStyle: 'sw-order-state__bg-neutral-icon-bg',
            selectBackgroundStyle: 'sw-order-state__bg-neutral-select',
            variant: 'neutral',
        },
    };

    const $icons = {
        neutral: 'small-arrow-small-down',
        progress: 'small-default-circle-small',
        danger: 'small-default-x-line-small',
        done: 'small-default-checkmark-line-small',
    };

    const $colors = {
        neutral: 'sw-order-state__neutral',
        progress: 'sw-order-state__progress',
        danger: 'sw-order-state__danger',
        done: 'sw-order-state__success',
    };

    const $variants = {
        neutral: 'neutral',
        progress: 'info',
        danger: 'danger',
        done: 'success',
    };

    return {
        getPlaceholder,
        addStyle,
        getStyle,
    };

    function getPlaceholder() {
        return $store.placeholder;
    }

    function addStyle(stateMachine, state, style) {
        if (!(stateMachine in $store)) {
            $store[stateMachine] = {};
        }

        const entry = deepCopyObject(getPlaceholder());

        if (style.icon in $icons) {
            entry.icon = $icons[style.icon];
        }

        if (style.color in $colors) {
            entry.iconStyle = `${$colors[style.color]}-icon`;
            entry.iconBackgroundStyle = `${$colors[style.color]}-icon-bg`;
            entry.selectBackgroundStyle = `${$colors[style.color]}-select`;
        }

        if (style.variant in $variants) {
            entry.variant = $variants[style.variant];
        }

        $store[stateMachine][state] = entry;
    }

    function getStyle(stateMachine, state) {
        if (stateMachine in $store && state in $store[stateMachine]) {
            return $store[stateMachine][state];
        }

        return getPlaceholder();
    }
}
