import { date } from 'src/core/service/utils/format.utils';
import { Filter } from 'src/core/shopware';

Filter.register('date', (value, locale, options) => {
    return date(value, locale, options);
});
