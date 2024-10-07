/**
 * @package admin
 *
 * @private
 */
export default (publicObject, privateObject) => {
    return (function ClassesBuilder() {
        // public functions
        const Classes = function Classes() {
            Object.entries(publicObject).forEach(
                ([
                    key,
                    value,
                ]) => {
                    Object.defineProperty(this, key, {
                        value,
                        configurable: true,
                        enumerable: true,
                        writable: true,
                    });
                },
            );
        };

        // private functions
        Classes.prototype = privateObject;

        return new Classes();
    })();
};
