/**
 * @package admin
 */

const crypto = require('crypto');

export default {
    get(key) {
        const hash = crypto.createHash('sha1');
        hash.update(key);
        return hash.digest('hex');
    },
};
