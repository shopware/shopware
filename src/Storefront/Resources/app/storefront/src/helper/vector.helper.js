function argsToVector(args) {
    if (args[0] instanceof Vector) {
        return args[0];
    }

    if (Array.isArray(args[0])) {
        return new Vector(args[0]);
    }

    return new Vector(args);
}

class Vector {
    /**
     * @param {Array<Number>}args
     */
    constructor(args) {
        this.entries = args.map((arg, index) => {
            if (typeof arg !== 'number') {
                throw new Error(`[Vector] argument ${index} must be a number ${typeof arg} given.`);
            }

            return arg;
        });
    }

    get dimension() {
        return this.entries.length;
    }

    validateDimensions(vectorB) {
        if (this.dimension !== vectorB.dimension) {
            throw new Error(`[Vector] dimension mismatch expected ${this.dimension} got ${vectorB.dimension}`);
        }
    }

    /* Make common entry names available */
    get x() { return this.entries[0]; }
    set x(x) { this.entries[0] = x; }

    get y() { return this.entries[1]; }
    set y(y) { if (this.dimension > 1) this.entries[1] = y; }

    get z() { return this.entries[2]; }
    set z(z) { if (this.dimension > 2) this.entries[2] = z; }

    get w() { return this.entries[3]; }
    set w(w) { if (this.dimension > 3) this.entries[3] = w; }

    /**
     * returns the euclid length of a vector
     *
     * @returns {number}
     */
    length() {
        return Math.sqrt(this.entries.reduce((acc, e) => {
            acc += e*e;
            return acc;
        }, 0));
    }

    /**
     * Vector addition
     *
     * @param {Vector} vector
     * @returns {Vector}
     */
    add(vector) {
        this.validateDimensions(vector);
        return new this.constructor(this.entries.map((e, index) => e + vector.entries[index]));
    }

    /**
     * Scalar multiplication of vertices
     *
     * @param {Vector|number} factor
     * @returns {Vector}
     */
    multiply(factor) {
        if (factor instanceof Vector) {
            this.validateDimensions(factor);
            return new this.constructor(this.entries.map((e, index) => {
                return e * factor.entries[index];
            }));
        }

        if (typeof factor !== 'number' || Number.isNaN(factor)) {
            throw new Error('[Vector] multiply: factor must be number or vector');
        }

        return new this.constructor(this.entries.map(e => factor * e));
    }

    /**
     * Add the negated vector
     *
     * @param {Vector} vector
     * @returns {Vector}
     */
    subtract(vector) {
        return this.add(vector.multiply(-1));
    }

    /**
     * Scalar multiplication with inverted number
     *
     * @param {Vector|number} quotient
     * @returns {Vector}
     */
    divide(quotient) {
        if (quotient instanceof Vector) {
            return new this.constructor(this.entries.map((e, index) => {
                return e / quotient.entries[index];
            }));
        }

        if (quotient === 0) {
            throw new Error('Can\'t divide by 0');
        }

        return this.multiply(1 / quotient);
    }

    /**
     * Returns unified vector
     *
     * @returns {Vector}
     */
    normalize() {
        return new this.constructor(this.divide(this.length()));
    }

    /**
     * compares dimension and entries of a vector
     *
     * @param {Vector,Array<number>}args
     * @returns {boolean}
     */
    equals(...args) {
        const other = argsToVector(args);

        try {
            this.validateDimensions(other);
            return this.entries.reduce((acc, e, index) => {
                if (e !== other.entries[index]) {
                    acc = false;
                }

                return acc;
            }, true);
        } catch (e) {
            return false;
        }
    }

    /**
     * Returns dot product of vertices
     * @param vector
     * @returns {*}
     */
    dot(vector) {
        this.validateDimensions(vector);

        return this.entries.reduce((acc, e, index) => {
            acc += e * vector.entries[index];
            return acc;
        }, 0);
    }

    /**
     *
     * @param {Vector|number} min
     * @param {Vector|number} max
     * @returns {*}
     */
    clamp(min, max) {
        if (typeof min === 'number') {
            min = new this.constructor((new Array(this.dimension)).fill(min));
        }

        if (typeof max === 'number') {
            max = new this.constructor((new Array(this.dimension)).fill(max));
        }

        return new this.constructor(this.entries.map((e, index) => {
            if (e < min.entries[index]) {
                return min.entries[index];
            }

            if (e > max.entries[index]) {
                return max.entries[index];
            }

            return e;
        }));
    }
}

/**
 * Vector2
 */
class Vector2 extends Vector {
    constructor(x, y) {
        if (x instanceof Vector) {
            super(x.entries.slice(0, 2));
            return;
        }

        if (Array.isArray(x)) {
            super(x.slice(0,2));
            return;
        }

        super([x, y]);
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
    constructor(x, y, z) {
        if (x instanceof Vector) {
            super(x.entries.slice(0, 3));
            return;
        }

        if (Array.isArray(x)) {
            super(x.slice(0,3));
            return;
        }

        super([x, y, z]);
    }

    cross(vector) {
        return new this.constructor(
            (this.y * vector.z - this.z * vector.y),
            (this.z * vector.x - this.x * vector.z),
            (this.x * vector.y - this.y * vector.x)
        );
    }
}

/**
 * Vector4
 */
class Vector4 extends Vector {
    constructor(x, y, z, w) {
        if (x instanceof Vector) {
            super(x.entries.slice(0, 4));
            return;
        }

        if (Array.isArray(x)) {
            super(x.slice(0,4));
            return;
        }

        super([x, y, z, w]);
    }
}

export default Vector;

export { Vector2, Vector3, Vector4};
