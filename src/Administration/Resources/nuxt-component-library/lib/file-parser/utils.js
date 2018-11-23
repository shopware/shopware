const glob = require('glob');

module.exports = {
    glob(pattern, options) {
        options = options || {};

        return new Promise((resolve, reject) => {
            glob(pattern, options, (err, files) => {
                if (err) {
                    reject(new Error(err));
                    return;
                }

                resolve(files);
            })
        });
    }
};
