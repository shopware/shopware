export type Task = (...args: unknown[]) => () => Promise<void>;

