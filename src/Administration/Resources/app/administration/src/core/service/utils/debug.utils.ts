export default {
    warn,
    error,
};

/**
 * General logging function which provides a unified style of log messages for developers. Please keep in mind the log
 * messages will be displayed in the developer console when they're running the application in development mode.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function warn(name = 'Core', ...message: any[]): void {
    if (process.env.NODE_ENV !== 'production' && typeof console !== 'undefined') {
        message.unshift(`[${name}]`);
        // @ts-expect-error
        console.warn.apply(this, message);
    }
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function error(name = 'Core', ...message: any[]): void {
    if (process.env.NODE_ENV !== 'production' && typeof console !== 'undefined') {
        message.unshift(`[${name}]`);
        // @ts-expect-error
        console.error.apply(this, message);
    }
}
