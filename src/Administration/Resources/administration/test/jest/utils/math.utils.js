export default {
    add,
    subtract
};

export function add(a, b) {
    return a + b;
}

export function subtract(a, b) {
    return add(a, -b);
}
