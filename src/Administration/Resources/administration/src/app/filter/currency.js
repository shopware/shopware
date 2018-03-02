import utils from 'src/core/service/util.service';
import { Filter } from 'src/core/shopware';

Filter.register('currency', (value, format = 'EUR') => {
    return utils.currency(value, format);
});
