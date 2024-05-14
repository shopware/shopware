import crypto from 'crypto';
import { stringify } from 'uuid';

interface IdPair {
    id: string;
    uuid: string;
}

export class IdProvider {
    constructor(
        private workerIndex: number,
        private seed: string
    ) {}

    getIdPair(): IdPair {
        return {
            id: `${crypto.randomInt(0, 281474976710655)}`,
            uuid: crypto.randomUUID().replaceAll('-', ''),
        };
    }

    getUniqueName(): string {
        return `name_${this.getIdPair().id}`;
    }

    getWorkerDerivedStableId(key: string): IdPair {
        // TODO: make it depend on the access key id
        const hash = crypto.createHash('sha256');
        hash.update(this.seed);
        hash.update(key);
        hash.update(`${this.workerIndex}`);

        const buffer = hash.digest();
        const bytes = Uint8Array.from(buffer).slice(0, 16);

        bytes[6] = 1 << 5; // set version to 4
        bytes[8] = 0x80;

        return {
            id: `${this.workerIndex}`,
            uuid: stringify(bytes).replaceAll('-', ''),
        };
    }
}
