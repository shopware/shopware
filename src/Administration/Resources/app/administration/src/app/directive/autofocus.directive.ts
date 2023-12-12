/**
 * @package admin
 */

Shopware.Directive.register('autofocus', window._features_?.vue3
    ? {
        mounted: (el: HTMLElement) => {
            const inputs = el.getElementsByTagName('input');

            if (inputs.length === 0) {
                return;
            }

            inputs[0].focus();
        },
    }
    : {
        // @ts-expect-error
        inserted: (el: HTMLElement) => {
            const inputs = el.getElementsByTagName('input');

            if (inputs.length === 0) {
                return;
            }

            inputs[0].focus();
        },
    });

/**
 * @private
 */
export {};
