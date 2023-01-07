/**
 * @package admin
 */

import utils from 'src/core/service/util.service';

export default class IdCollection {
    ids = {};

    get(key) {
        if (!this.ids.hasOwnProperty(key)) {
            this.ids[key] = utils.createId();
        }

        return this.ids[key];
    }

    getList(keys) {
        const ids = [];

        keys.forEach((key) => {
            ids.push(this.get(key));
        });

        return ids;
    }
}
