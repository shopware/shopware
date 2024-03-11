/**
 * @package admin
 */

Shopware.Directive.register('autofocus', {
    mounted: (el: HTMLElement) => {
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
