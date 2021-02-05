/* global test, expect, describe */
import Vector, { Vector2, Vector3, Vector4 } from 'src/helper/vector.helper';

describe('Vector tests', () => {
    describe('constructor', () => {
        test('expect objects are vertices', () => {
            expect(new Vector2(1,2)).toBeInstanceOf(Vector);
            expect(new Vector3(1,2,3)).toBeInstanceOf(Vector);
            expect(new Vector4(1,2,3,4)).toBeInstanceOf(Vector);
        });

        test('has correct vertex amount', () => {
            const vec = new Vector4(1, 1, 1, 1);
            expect(vec.dimension).toBe(4);
        });

        test('it throws for objects being passed as constructor elements', () => {
            expect(() => { new Vector2({x: 2, y: 4}); }).toThrowError();
        });
    });

    describe('Vector2 constructor', () => {
        test('it can be created by numbers', () => {
            const vec = new Vector2(1, 2);
            expect(vec.entries).toStrictEqual([1, 2]);
        });

        test('it can be created by array', () => {
            const vec = new Vector2([1, 2]);
            expect(vec.entries).toStrictEqual([1, 2]);
        });

        test('it can be created by other vector', () => {
            const vec = new Vector2(new Vector([1, 2]));
            expect(vec.entries).toStrictEqual([1, 2]);
        });
    });

    describe('Vector3 constructor', () => {
        test('it can be created by numbers', () => {
            const vec = new Vector3(1, 2, 3);
            expect(vec.entries).toStrictEqual([1, 2, 3]);
        });

        test('it can be created by array', () => {
            const vec = new Vector3([1, 2, 3]);
            expect(vec.entries).toStrictEqual([1, 2, 3]);
        });

        test('it can be created by other vector', () => {
            const vec = new Vector3(new Vector([1, 2, 3]));
            expect(vec.entries).toStrictEqual([1, 2, 3]);
        });
    });

    describe('Vector4 constructor', () => {
        test('it can be created by numbers', () => {
            const vec = new Vector4(1, 2, 3, 4);
            expect(vec.entries).toStrictEqual([1, 2, 3, 4]);
        });

        test('it can be created by array', () => {
            const vec = new Vector4([1, 2, 3, 4]);
            expect(vec.entries).toStrictEqual([1, 2, 3, 4]);
        });

        test('it can be created by other vector', () => {
            const vec = new Vector4(new Vector([1, 2, 3, 4]));
            expect(vec.entries).toStrictEqual([1, 2, 3, 4]);
        });
    });

    describe('methods', () => {
        describe('length', () => {
            test('get length', () => {
                const vec = new Vector4(1, 2, 3, 4);
                expect(vec.length()).toBeCloseTo(5.477225575051661);
            });
        });

        describe('normalize', () => {
            test('normalized vector', () => {
                const vec = new Vector4(1, 2, 3, 4);
                const newVec = vec.normalize();
                expect(newVec.length()).toBeCloseTo(1);
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
                expect(vec.dot(new Vector4(4, 4, 4, 4))).toBe(40);
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

            test('throw when multiplied with non numbers', () => {
                expect(() => { (new Vector2(1,2)).multiply(false); }).toThrowError();
                expect(() => { (new Vector2(1,2)).multiply(null); }).toThrowError();
                expect(() => { (new Vector2(1,2)).multiply(undefined); }).toThrowError();
                expect(() => { (new Vector2(1,2)).multiply({x: 1, y: 2}); }).toThrowError();
            })
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
                expect(vec.divide.bind(vec, 0)).toThrowError();
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

            test('clamp returns original vector if in range', () => {
                const min = new Vector3(0,0,0);
                const max = new Vector3(10,10,10);
                const clamped = (new Vector3(5,5,5)).clamp(min, max);

                expect(clamped.x).toEqual(5);
                expect(clamped.y).toEqual(5);
                expect(clamped.z).toEqual(5);
            })
        });

        describe('equals', () => {
            test('equal vertex', () => {
                const vec = new Vector4(1, 2, 3, 4);
                const expected = new Vector4(1, 2, 3, 4);
                expect(vec.equals(expected)).toBe(true);
            });

            test('not equal vertex', () => {
                const vec = new Vector4(4, 3, 2, 1);
                const expected = new Vector4(1, 2, 3, 4);
                expect(vec.equals(expected)).not.toBe(true);
            });

            test('equal vertex with numbers', () => {
                const vec = new Vector4(1, 2, 3, 4);
                expect(vec.equals(1, 2, 3, 4)).toBe(true);
            });

            test('does not equal vertices from other dimension', () => {
                expect((new Vector2(1,2)).equals(new Vector3(1,2,3))).toBe(false);
            })
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

    describe('Vector3  methods', () => {
        describe('cross', () => {
            test('cross vector', () => {
                const vec1 = new Vector3(1, 2, 3);
                const vec2 = new Vector3(4, 3, 2);
                const crossProduct = vec1.cross(vec2);

                expect(crossProduct.dot(vec1)).toBeCloseTo(0);
                expect(crossProduct.dot(vec2)).toBeCloseTo(0);
            });

            test('same vector has no cross product', () => {
                const vec1 = new Vector3(1, 2, 3);
                expect(new Vector3(0, 0, 0).equals(vec1.cross(vec1))).toBe(true);
            })
        });
    })
});
