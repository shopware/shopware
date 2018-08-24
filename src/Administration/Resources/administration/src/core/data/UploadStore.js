/**
 * @module core/data/UploadStore
 */
import noop from 'lodash/noop'
import remove from 'lodash/remove';
import UploadTask from 'src/core/helper/uploadTask.helper';

class UploadStore {
    constructor() {
        this.tags = new Map();
    }

    isTagMissing(tag) {
        return !this.tags.has(tag);
    }

    addUpload(tag, uploadFunction) {
        if (this.isTagMissing(tag)) {
            this.tags.set(tag, []);
        }

        const task = new UploadTask(uploadFunction);

        this.tags.get(tag).push(task);

        return task;
    }

    removeUpload(id) {
        this.tags.forEach((taskCollection, tag) => {
            remove(taskCollection, (task) => {
                return task.id === id;
            });

            if (taskCollection.length === 0) {
                this.tags.delete(tag);
            }
        });
    }

    runUploads(tag, callback = noop) {
        if (this.isTagMissing(tag)) {
            return Promise.resolve({});
        }

        return Promise.all(this.tags.get(tag).map((task) => {
            return task.start().then(() => {
                callback.apply(null, [this.getRunningTaskCount(tag)]);
            });
        })).finally(() => {
            this.tags.delete(tag);
        });
    }

    getRunningTaskCount(tag) {
        if (this.isTagMissing(tag)) {
            return 0;
        }
        return this.tags.get(tag).reduce((total, task) => {
            return task.running ? total + 1 : total;
        }, 0);
    }

    getPendingTaskCount(tag) {
        if (this.isTagMissing(tag)) {
            return 0;
        }
        return this.tags.get(tag).reduce((total, task) => {
            const isPending = !task.running && !task.resolved;
            return isPending ? total + 1 : total;
        }, 0);
    }
}

export default UploadStore;
