import { date } from 'src/core/service/utils/format.utils';
import { Filter } from 'src/core/shopware';

Filter.register('date', (value, options) => {
    if (!value) {
        return '';
    }

    return date(value, options);
});
