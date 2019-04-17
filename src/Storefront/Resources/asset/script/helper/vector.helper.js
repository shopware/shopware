const VERTICES = ['x', 'y', 'z', 'w'];

/**
 * VectorBase class
 */
class VectorBase {

    constructor(verticesCount, ...rest) {
        this._verticesCount = verticesCount;

        this._iterateVertices((vertex, key) => {
            this[vertex] = rest[key];
        });
    }

    /**
     * iterates over all available vertices
     *
     * @param {function} callback
     * @private
     */
    _iterateVertices(callback) {
        for (let index = 0; index < this._verticesCount; index++) {
            const vertex = VERTICES[index];
            callback(vertex, index);
        }
    }

    /**
     * when args
     *  - is a vector it iterates over the vector
     *  - is a single number it uses it for all vector values
     *  - is a number array it uses them for the vector values
     *
     * @param {Array} args
     * @param {function} callback
     * @return {Array}
     *
     * @private
     */
    _parseArguments(args, callback) {
        const parameters = [];

        if (typeof args === 'number') {
            this._iterateVertices((vertex, index) => {
                parameters.push(callback(this[vertex], args, index));
            });
        } else if (args[0] instanceof VectorBase) {
            this._validate(args[0], true);
            this._iterateVertices((vertex, index) => {
                parameters.push(callback(this[vertex], args[0][vertex], index));
            });
        } else if (args instanceof VectorBase) {
            this._validate(args, true);
            this._iterateVertices((vertex, index) => {
                parameters.push(callback(this[vertex], args[vertex], index));
            });
        } else if (typeof args[0] === 'number' || typeof args[0] === 'boolean') {
            if (typeof args[1] !== 'number' && typeof args[1] !== 'boolean') {
                this._iterateVertices((vertex, index) => {
                    if (args[0] === false) {
                        parameters.push(this[vertex]);
                    } else {
                        parameters.push(callback(this[vertex], args[0], index));
                    }
                });
            } else {
                this._iterateVertices((vertex, index) => {
                    if (typeof args[index] !== 'number' && typeof args[index] !== 'boolean') {
                        throw new Error(`Parameter ${index + 1} must be a Number or Boolean`);
                    }
                    if (args[index] === false) {
                        parameters.push(this[vertex]);
                    } else {
                        parameters.push(callback(this[vertex], args[index], index));
                    }
                });
            }
        }

        return parameters;
    }

    /**
     * validates a vector to be equal to the current one
     *
     * @param vector
     * @param strict
     *
     * @private
     */
    _validate(vector, strict = false) {
        if (!vector) {
            throw new Error('A vector must be passed.');
        }
        if (strict && vector.constructor.name !== this.constructor.name) {
            throw new Error(`${this.constructor.name} must be passed.`);
        }
    }

}

export default class Vector extends VectorBase {

    /**
     * sets the vector values
     * @param {VectorBase, Number} args
     */
    set(...args) {
        const parameters = this._parseArguments(args, (currentVertex, passedVertex) => {
            return passedVertex;
        });

        return new this.constructor(...parameters);
    }

    /**
     * returns length of the vector
     *
     * @return {number}
     */
    length() {
        let val = 0;
        this._iterateVertices(vertex => {
            val += this[vertex] * this[vertex];
        });

        return Math.sqrt(Math.abs(val));
    }

    /**
     * linear extrapolation between two vectors
     *
     *
     * @param {VectorBase, Number} args
     * @param factor
     * @return {*}
     */
    lerp(args, factor) {
        const parameters = this._parseArguments(args, (currentVertex, passedVertex) => passedVertex);
        const vertex = new this.constructor(...parameters);
        return vertex.subtract(this).multiply(factor).add(this);
    }

    /**
     * normalizes the vector
     *
     * @return {VectorBase}
     */
    normalize() {
        let len = this.length();
        const parameters = [];

        if (len > 0) {
            len = 1 / Math.sqrt(len);

            this._iterateVertices(vertex => {
                parameters.push(this[vertex] * len);
            });

            return new this.constructor(...parameters);
        }

        return this;
    }

    /**
     * floors the vector
     *
     * @return {VectorBase}
     */
    floor() {
        const parameters = [];

        this._iterateVertices(vertex => {
            parameters.push(Math.floor(this[vertex]));
        });

        return new this.constructor(...parameters);
    }

    /**
     * ceils the vector
     *
     * @return {VectorBase}
     */
    ceil() {
        const parameters = [];

        this._iterateVertices(vertex => {
            parameters.push(Math.ceil(this[vertex]));
        });

        return new this.constructor(...parameters);
    }

    /**
     * rounds the vector
     *
     * @return {VectorBase}
     */
    round() {
        const parameters = [];

        this._iterateVertices(vertex => {
            parameters.push(Math.round(this[vertex]));
        });

        return new this.constructor(...parameters);
    }

    /**
     * crossnumber between two vectors
     *
     * @param {VectorBase, Number} args
     * @return {number}
     */
    cross(...args) {
        let ret = 0;

        this._parseArguments(args, (currentVertex, passedVertex) => {
            ret -= currentVertex * passedVertex;
        });

        return ret;
    }

    /**
     * returns the scalar product of two vectors
     *
     * @param {VectorBase, Number} args
     * @return {number}
     */
    dot(...args) {
        let ret = 0;

        this._parseArguments(args, (currentVertex, passedVertex) => {
            ret += parseFloat(currentVertex * passedVertex);
        });

        return ret;
    }

    /**
     * adds two vectors together
     *
     * @param {VectorBase, Number} args
     * @return {VectorBase}
     */
    add(...args) {
        const parameters = this._parseArguments(args, (currentVertex, passedVertex) => {
            return currentVertex + passedVertex;
        });

        return new this.constructor(...parameters);
    }

    /**
     * subtracts two vectors from each other
     *
     * @param {VectorBase, Number} args
     * @return {VectorBase}
     */
    subtract(...args) {
        const parameters = this._parseArguments(args, (currentVertex, passedVertex) => {
            return currentVertex - passedVertex;
        });

        return new this.constructor(...parameters);
    }

    /**
     * multiplies two vectors with each other
     *
     * @param {VectorBase, Number} args
     * @return {VectorBase}
     */
    multiply(...args) {
        const parameters = this._parseArguments(args, (currentVertex, passedVertex) => {
            return currentVertex * passedVertex;
        });

        return new this.constructor(...parameters);
    }

    /**
     * divides two vectors with each other
     *
     * @param {VectorBase, Number} args
     * @return {VectorBase}
     */
    divide(...args) {
        const parameters = this._parseArguments(args, (currentVertex, passedVertex) => {
            if (passedVertex === 0) {
                throw new Error('Can\'t divide by 0');
            }
            return currentVertex / passedVertex;
        });

        return new this.constructor(...parameters);
    }

    /**
     * clamps vector to min/max value
     *
     * @param {VectorBase|number} min
     * @param {VectorBase|number} max
     * @return {VectorBase}
     */
    clamp(min, max) {
        const parameters = [];

        this._parseArguments(min, (currentVertex, passedVertex, index) => {
            parameters[index] = Math.max(currentVertex, passedVertex);
        });

        this._parseArguments(max, (currentVertex, passedVertex, index) => {
            parameters[index] = Math.min(parameters[index], passedVertex);
        });

        return new this.constructor(...parameters);
    }

    /**
     * returns absolute values of the vector
     *
     * @return {VectorBase}
     */
    abs() {
        const parameters = [];

        this._iterateVertices(vertex => {
            parameters.push(Math.abs(this[vertex]));
        });

        return new this.constructor(...parameters);
    }

    /**
     * compares two vectors
     *
     * @param {VectorBase, Number} args
     * @return {boolean}
     */
    equals(...args) {
        const truthy = [];

        this._parseArguments(args, (currentVertex, passedVertex) => {
            truthy.push(currentVertex === passedVertex);
        });

        return truthy.indexOf(false) === -1;
    }

}


/**
 * Vector2
 */
class Vector2 extends Vector {

    constructor(x = 0, y = 0) {
        super(2, x, y);
    }

    /**
     * calculates the angle
     *
     * @return {number}
     */
    angle() {
        const angle = Math.atan2(this.y, this.x) * (180 / Math.PI);
        return (angle + 360) % 360;
    }

}

/**
 * Vector3
 */
class Vector3 extends Vector {

    constructor(x = 0, y = 0, z = 0) {
        super(3, x, y, z);
    }

}

/**
 * Vector4
 */
class Vector4 extends Vector {

    constructor(x = 0, y = 0, z = 0, w = 0) {
        super(4, x, y, z, w);
    }

}

export {
    Vector2,
    Vector3,
    Vector4,
};

