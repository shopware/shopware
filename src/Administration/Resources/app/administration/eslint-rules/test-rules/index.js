/**
 * @sw-package framework
 */

module.exports = {
    rules: {
        'await-async-functions': require('./await-async-functions'),
        'stabilize-feature-flag': require('./stabilize-feature-flag'),
        'test-file-max-lines-warning': require('./test-file-max-lines/warning'),
        'test-file-max-lines-error': require('./test-file-max-lines/error'),
        'no-vm-access': require('./no-vm-access'),
        'test-annotation': require('./test-annotation'),
        'test-annotation-see-exception': require('./test-annotation/see-exception'),
    },
};
