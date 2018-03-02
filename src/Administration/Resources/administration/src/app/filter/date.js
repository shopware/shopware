import utils from 'src/core/service/util.service';
import { Filter } from 'src/core/shopware';

Filter.register('date', (value) => {
    return utils.date(value);
});
