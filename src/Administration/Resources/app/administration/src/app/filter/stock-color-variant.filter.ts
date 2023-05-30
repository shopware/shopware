/**
 * @package inventory
 */

Shopware.Filter.register('stockColorVariant', (value: number) => {
    if (typeof value !== 'number' && !value) {
        return '';
    }

    if (value >= 25) {
        return 'success';
    }

    if (value < 25 && value > 0) {
        return 'warning';
    }

    return 'error';
});

/* @private */
export {};
