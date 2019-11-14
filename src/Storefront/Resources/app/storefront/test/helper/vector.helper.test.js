/* global test, expect, describe */
import Vector, { Vector2, Vector3, Vector4 } from 'src/helper/vector.helper';

describe('Vector tests', () => {


    test('all vectors have the vector base class', () => {
        const Vector2Base = Vector2.__proto__;
        const Vector3Base = Vector3.__proto__;
        const Vector4Base = Vector4.__proto__;

        expect(Vector2Base).toBe(Vector);
        expect(Vector3Base).toBe(Vector);
        expect(Vector4Base).toBe(Vector);
    });


    test('has correct vertex amount', () => {
        const vec = new Vector4(1, 1, 1, 1);
        expect(vec._verticesCount).toBe(4);
    });

    test('can contain decimal values ', () => {
        const vec = new Vector4(0.1, 0.2, 0.3, 0.4);
        expect(vec.x).toEqual(0.1);
        expect(vec.y).toEqual(0.2);
        expect(vec.z).toEqual(0.3);
        expect(vec.w).toEqual(0.4);
    });

    describe('methods', () => {
        describe('set', () => {
            test('set values from vector', () => {
                const vec = new Vector4(0, 0, 0, 0);
                const newVec = vec.set(new Vector4(1, 2, 3, 4));
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });

            test('set values negative values from vector', () => {
                const vec = new Vector4(0, 0, 0, 0);
                const newVec = vec.set(new Vector4(-1, -2, -3, -4));
                expect(newVec.x).toEqual(-1);
                expect(newVec.y).toEqual(-2);
                expect(newVec.z).toEqual(-3);
                expect(newVec.w).toEqual(-4);
            });

            test('set values from numbers', () => {
                const vec = new Vector4(0, 0, 0, 0);
                const newVec = vec.set(1, 2, 3, 4);
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });

            test('set values with error', () => {
                const vec = new Vector4(0, 0, 0, 0);
                expect(vec.set.bind(vec, 2, 3)).toThrow(new Error('Parameter 3 must be a Number or Boolean'));
            });
        });

        describe('length', () => {
            test('get length', () => {
                const vec = new Vector4(1, 2, 3, 4);
                expect(vec.length()).toBeCloseTo(5.477225575051661);
            });

            test('get length reversed', () => {
                const vec = new Vector4(4, 3, 2, 1);
                expect(vec.length()).toBeCloseTo(5.477225575051661);
            });
        });

        describe('lerp', () => {
            test('lerp to middle', () => {
                const vec = new Vector4(0, 0, 0, 0);
                const lerpVec = new Vector4(2, 2, 2, 2);
                expect(vec.lerp(lerpVec, 0.5)).toEqual(new Vector4(1, 1, 1, 1));
            });

            test('lerp to middle with number', () => {
                const vec = new Vector4(0, 0, 0, 0);
                expect(vec.lerp(5, 0.5)).toEqual(new Vector4(2.5, 2.5, 2.5, 2.5));
            });
        });

        describe('normalize', () => {
            test('normalized vector', () => {
                const vec = new Vector4(1, 2, 3, 4);
                const newVec = vec.normalize();
                expect(newVec.x).toBeCloseTo(0.42728700639623407);
                expect(newVec.y).toBeCloseTo(0.8545740127924681);
                expect(newVec.z).toBeCloseTo(1.2818610191887023);
                expect(newVec.w).toBeCloseTo(1.7091480255849363);
            });
        });

        describe('floor', () => {
            test('floor vector', () => {
                const vec = new Vector4(1.23, 2.34, 3.41, 4.12);
                const newVec = vec.floor();
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });

            test('floor negative vector', () => {
                const vec = new Vector4(-1.23, -2.34, -3.41, -4.12);
                const newVec = vec.floor();
                expect(newVec.x).toEqual(-2);
                expect(newVec.y).toEqual(-3);
                expect(newVec.z).toEqual(-4);
                expect(newVec.w).toEqual(-5);
            });
        });

        describe('ceil', () => {
            test('ceil vector', () => {
                const vec = new Vector4(1.23, 2.34, 3.41, 4.12);
                const newVec = vec.ceil();
                expect(newVec.x).toEqual(2);
                expect(newVec.y).toEqual(3);
                expect(newVec.z).toEqual(4);
                expect(newVec.w).toEqual(5);
            });

            test('ceil negative vector', () => {
                const vec = new Vector4(-1.23, -2.34, -3.41, -4.12);
                const newVec = vec.ceil();
                expect(newVec.x).toEqual(-1);
                expect(newVec.y).toEqual(-2);
                expect(newVec.z).toEqual(-3);
                expect(newVec.w).toEqual(-4);
            });
        });

        describe('round', () => {
            test('round vector', () => {
                const vec = new Vector4(1.23, 2.34, 3.41, 4.12);
                const newVec = vec.round();
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });

            test('round negative vector', () => {
                const vec = new Vector4(-1.23, -2.34, -3.41, -4.12);
                const newVec = vec.round();
                expect(newVec.x).toEqual(-1);
                expect(newVec.y).toEqual(-2);
                expect(newVec.z).toEqual(-3);
                expect(newVec.w).toEqual(-4);
            });
        });

        describe('cross', () => {
            test('cross vector', () => {
                const vec = new Vector4(1, 2, 3, 4);
                expect(vec.cross(new Vector4(4, 3, 2, 1))).toEqual(-20);
            });

            test('cross parameters', () => {
                const vec = new Vector4(1, 2, 3, 4);
                expect(vec.cross(4, 3, 2, 1)).toEqual(-20);
            });

            test('cross negative vector', () => {
                const vec = new Vector4(-1, -2, -3, -4);
                expect(vec.cross(new Vector4(4, 3, 2, 1))).toEqual(20);
            });

            test('cross negative parameters', () => {
                const vec = new Vector4(-1, -2, -3, -4);
                expect(vec.cross(4, 3, 2, 1)).toEqual(20);
            });
        });

        describe('dot', () => {
            test('get dot product', () => {
                const vec = new Vector4(1, 2, 3, 4);
                expect(vec.dot(new Vector4(4, 3, 2, 1))).toBe(20);
            });

            test('get dot product from negative vector', () => {
                const vec = new Vector4(-1, -2, -3, -4);
                expect(vec.dot(new Vector4(4, 3, 2, 1))).toBe(-20);
            });

            test('get dot product with number', () => {
                const vec = new Vector4(1, 2, 3, 4);
                expect(vec.dot(4)).toBe(40);
            });
        });

        describe('add', () => {
            test('add vectors', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.add(new Vector4(0.5, 1, 1.5, 2));
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });

            test('add negative vectors', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.add(new Vector4(-0.5, -1, -1.5, -2));
                expect(newVec.x).toEqual(0);
                expect(newVec.y).toEqual(0);
                expect(newVec.z).toEqual(0);
                expect(newVec.w).toEqual(0);
            });
        });

        describe('subtract', () => {
            test('subtract vectors', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.subtract(new Vector4(0.5, 1, 1.5, 2));
                expect(newVec.x).toEqual(0);
                expect(newVec.y).toEqual(0);
                expect(newVec.z).toEqual(0);
                expect(newVec.w).toEqual(0);
            });

            test('subtract negative vectors', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.subtract(new Vector4(-0.5, -1, -1.5, -2));
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });
        });

        describe('multiply', () => {
            test('multiply vectors', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.multiply(new Vector4(2, 2, 2, 2));
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });

            test('multiply negative vectors', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.multiply(new Vector4(-2, -2, -2, -2));
                expect(newVec.x).toEqual(-1);
                expect(newVec.y).toEqual(-2);
                expect(newVec.z).toEqual(-3);
                expect(newVec.w).toEqual(-4);
            });

            test('multiply number', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.multiply(2);
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });

            test('multiply negative number', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.multiply(-2);
                expect(newVec.x).toEqual(-1);
                expect(newVec.y).toEqual(-2);
                expect(newVec.z).toEqual(-3);
                expect(newVec.w).toEqual(-4);
            });

            test('multiply numbers', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.multiply(2, 2, 2, 2);
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });

            test('multiply negative numbers', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.multiply(-2, -2, -2, -2);
                expect(newVec.x).toEqual(-1);
                expect(newVec.y).toEqual(-2);
                expect(newVec.z).toEqual(-3);
                expect(newVec.w).toEqual(-4);
            });
        });

        describe('divide', () => {
            test('divide vectors', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.divide(new Vector4(2, 2, 2, 2));
                expect(newVec.x).toEqual(0.25);
                expect(newVec.y).toEqual(0.5);
                expect(newVec.z).toEqual(0.75);
                expect(newVec.w).toEqual(1);
            });

            test('divide negative vectors', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.divide(new Vector4(-2, -2, -2, -2));
                expect(newVec.x).toEqual(-0.25);
                expect(newVec.y).toEqual(-0.5);
                expect(newVec.z).toEqual(-0.75);
                expect(newVec.w).toEqual(-1);
            });

            test('divide number', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.divide(2);
                expect(newVec.x).toEqual(0.25);
                expect(newVec.y).toEqual(0.5);
                expect(newVec.z).toEqual(0.75);
                expect(newVec.w).toEqual(1);
            });

            test('divide negative number', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.divide(-2);
                expect(newVec.x).toEqual(-0.25);
                expect(newVec.y).toEqual(-0.5);
                expect(newVec.z).toEqual(-0.75);
                expect(newVec.w).toEqual(-1);
            });

            test('divide numbers', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.divide(2, 2, 2, 2);
                expect(newVec.x).toEqual(0.25);
                expect(newVec.y).toEqual(0.5);
                expect(newVec.z).toEqual(0.75);
                expect(newVec.w).toEqual(1);
            });

            test('divide negative numbers', () => {
                const vec = new Vector4(0.5, 1, 1.5, 2);
                const newVec = vec.divide(-2, -2, -2, -2);
                expect(newVec.x).toEqual(-0.25);
                expect(newVec.y).toEqual(-0.5);
                expect(newVec.z).toEqual(-0.75);
                expect(newVec.w).toEqual(-1);
            });

            test('throw error when divided by 0', () => {
                const vec = new Vector4(1, 2, 3, 4);
                expect(vec.divide.bind(vec, 0)).toThrow(Error);
            });
        });

        describe('clamp', () => {
            test('clamp with vectors', () => {
                const vec = new Vector4(11, 22, 33, 44);
                const clampMinVec = new Vector4(0, 0, 0, 0);
                const clampMaxVec = new Vector4(1, 2, 3, 4);
                const newVec = vec.clamp(clampMinVec, clampMaxVec);
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });

            test('clamp with numbers', () => {
                const vec = new Vector4(11, 22, 33, 44);
                const newVec = vec.clamp(27, 32);
                expect(newVec.x).toEqual(27);
                expect(newVec.y).toEqual(27);
                expect(newVec.z).toEqual(32);
                expect(newVec.w).toEqual(32);
            });

            test('clamp with vector and number', () => {
                const vec = new Vector4(11, 22, 33, 44);
                const newVec = vec.clamp(new Vector4(12, 23, 0, 0), 32);
                expect(newVec.x).toEqual(12);
                expect(newVec.y).toEqual(23);
                expect(newVec.z).toEqual(32);
                expect(newVec.w).toEqual(32);
            });
        });

        describe('abs', () => {
            test('absolute vertex', () => {
                const vec = new Vector4(-1, -2, -3, 4);
                const newVec = vec.abs();
                expect(newVec.x).toEqual(1);
                expect(newVec.y).toEqual(2);
                expect(newVec.z).toEqual(3);
                expect(newVec.w).toEqual(4);
            });

            test('absolute vertex with dezimal', () => {
                const vec = new Vector4(-0.1234, -0.2341, -0.3412, -0.4321);
                const newVec = vec.abs();
                expect(newVec.x).toEqual(0.1234);
                expect(newVec.y).toEqual(0.2341);
                expect(newVec.z).toEqual(0.3412);
                expect(newVec.w).toEqual(0.4321);
            });
        });

        describe('equals', () => {
            test('equal vertex', () => {
                const vec = new Vector4(1, 2, 3, 4);
                const expected = new Vector4(1, 2, 3, 4);
                expect(vec.equals(expected)).toBeTruthy();
            });

            test('not equal vertex', () => {
                const vec = new Vector4(4, 3, 2, 1);
                const expected = new Vector4(1, 2, 3, 4);
                expect(vec.equals(expected)).not.toBeTruthy();
            });

            test('equal vertex with numbers', () => {
                const vec = new Vector4(1, 2, 3, 4);
                expect(vec.equals(1, 2, 3, 4)).toBeTruthy();
            });
        });
    });

    describe('Vector2 methods', () => {

        test('angle', () => {
            expect(new Vector2(1, 1).angle()).toBe(45);
            expect(new Vector2(0, 1).angle()).toBe(90);
        });

        test('negative angle', () => {
            expect(new Vector2(-1, -1).angle()).toBe(225);
            expect(new Vector2(0, -1).angle()).toBe(270);
        });

        test('random angle', () => {
            expect(new Vector2(288, 335).angle()).toBeCloseTo(49.31430210716661);
            expect(new Vector2(197, 166).angle()).toBeCloseTo(40.11881527480966);
            expect(new Vector2(25, 199).angle()).toBeCloseTo(82.8395502731089);
            expect(new Vector2(131, 37).angle()).toBeCloseTo(15.771948056854853);
        });

    });
});
