import { Actor } from '@fixtures/Actor';

export type Task = (actor: Actor) => Promise<void>;

