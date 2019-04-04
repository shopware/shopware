const VERTICES = ['x', 'y', 'z', 'w'];

/**
 * VectorBase class
 */
class VectorBase {

    constructor(verticesCount, ...rest) {
        this._verticesCount = verticesCount;

        this._iterateVertices((vertex, key) => {
            this[vertex] = rest[key];
        })
    }

    /**
     * iterates over all available vertices
     *
     * @param cb
     * @private
     */
    _iterateVertices(cb) {
        for (let key = 0; key < this._verticesCount; key++) {
            const vertex = VERTICES[key];
            cb(vertex, key);
        }
    }

    /**
     * when args
     *  - is a vector it iterates over the vector
     *  - is a single number it uses it for all vector values
     *  - is a number array it uses them for the vector values
     *
     * @param args
     * @param cb
     * @return {Array}
     *
     * @private
     */
    _parseArguments(args, cb) {
        const parameters = [];

        if (args[0] instanceof VectorBase) {
            this._validate(args[0], true);
            this._iterateVertices(vertex => {
                parameters.push(cb(this[vertex], args[0][vertex]));
            });
        } else if (typeof args[0] === 'number' || typeof args[0] === 'boolean') {
            if (typeof args[1] !== 'number' && typeof args[1] !== 'boolean') {
                this._iterateVertices(vertex => {
                    if (args[0] === false) {
                        parameters.push(this[vertex]);
                    } else {
                        parameters.push(cb(this[vertex], args[0]));
                    }
                });
            } else {
                this._iterateVertices((vertex, key) => {
                    if (typeof args[key] !== 'number' && typeof args[key] !== 'boolean') {
                        throw new Error(`Parameter ${key + 1} must be a Number or Boolean`);
                    }
                    if (args[key] === false) {
                        parameters.push(this[vertex]);
                    } else {
                        parameters.push(cb(this[vertex], args[key]));
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

class Vector extends VectorBase {

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
     * @param vector
     * @param factor
     * @return {*}
     */
    lerp(vector, factor) {
        this._validate(vector, true);
        return vector.sub(this).mul(factor).add(this);
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
     * @param vector
     * @return {number}
     */
    cross(vector) {
        this._validate(vector, true);
        let ret = 0;

        this._iterateVertices(vertex => {
            ret -= this[vertex] * vector[vertex];
        });

        return ret;
    }

    /**
     * returns the scalar product of two vectors
     *
     * @param vector
     * @return {number}
     */
    dot(vector) {
        this._validate(vector, true);
        let ret = 0;

        this._iterateVertices(vertex => {
            ret += parseFloat(this[vertex] * vector[vertex]);
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
     * @param {VectorBase|number} minVecOrNum
     * @param {VectorBase|number} maxVecOrNum
     * @return {VectorBase}
     */
    clamp(minVecOrNum, maxVecOrNum) {
        const parameters = [];

        this._iterateVertices(vertex => {
            let min = minVecOrNum;
            if (typeof minVecOrNum !== 'number') {
                this._validate(minVecOrNum, true);
                min = minVecOrNum[vertex];
            }

            let max = maxVecOrNum;
            if (typeof maxVecOrNum !== 'number') {
                this._validate(maxVecOrNum, true);
                max = maxVecOrNum[vertex];
            }

            parameters.push(Math.max(min, Math.min(max, this[vertex])));
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
     * @param vector
     * @return {boolean}
     */
    equals(vector) {
        this._validate(vector, true);
        const truthy = [];

        this._iterateVertices(vertex => {
            truthy.push(this[vertex] === vector[vertex]);
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
        return Math.atan2(this.y, this.x);
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
}

