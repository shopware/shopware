import { currency } from 'src/core/service/utils/format.utils';
import { Filter } from 'src/core/shopware';

Filter.register('currency', (value, format = 'EUR') => {
    return currency(value, format);
});
