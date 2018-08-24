import UploadStore from 'src/core/data/UploadStore';
import noop from 'lodash/noop';
import times from 'lodash/times';
import rangeRight from 'lodash/rangeRight';

describe('src/core/data/uploadStore.js', () => {
    it('should save uploads', () => {
        const uStore = new UploadStore();
        const tag = 'tag1';

        const resultTask = uStore.addUpload(tag, noop);

        expect(uStore.tags).to.contain.all.keys(tag);
        expect(uStore.tags.get(tag)).to.have.lengthOf(1);
        expect(resultTask).to.have.property('id');
    });

    it('should count pending upload tasks for a tag', () => {
        const tags = ['tag1', 'tag1', 'tag2', 'tag2', 'tag3'];
        const uStore = new UploadStore();
        tags.forEach((tag) => {
            uStore.addUpload(tag, noop);
        });

        const count = uStore.getPendingTaskCount('tag1');

        expect(count).to.equal(2);
    });

    it('should count running upload tasks for a tag', () => {
        const uStore = new UploadStore();

        const testTasks = [
            { id: 0, running: true, resolved: false },
            { id: 1, running: true, resolved: false },
            { id: 2, running: false, resolved: true },
            { id: 3, running: false, resolved: true },
            { id: 4, running: false, resolved: true }
        ];
        const tag = 'tag1';

        uStore.tags.set(tag, testTasks);

        const count = uStore.getRunningTaskCount(tag);

        expect(count).to.equal(2);
    });

    it('should delete an upload by its ID', () => {
        const uStore = new UploadStore();
        const tag = 'tag1';

        const upload = uStore.addUpload(tag, noop);
        const uploadId = upload.id;

        uStore.removeUpload(uploadId);

        expect(uStore.tags.size).to.equal(0);
    });

    it('should run uploads', () => {
        const tag = 'tag1';
        let uploadCounter = 0;
        const testFn = () => {
            uploadCounter += 1;
        };

        const uStore = new UploadStore();
        times(10, () => {
            uStore.addUpload(tag, testFn);
        });

        return uStore.runUploads(tag).then(() => {
            expect(uStore.tags.size).to.equal(0);

            expect(uploadCounter).to.equal(10);
        });
    });

    it('should provide a callback mechanism for status updates', () => {
        const tag = 'tag1';
        const uploadCount = 10;
        const uStore = new UploadStore();

        // save 10 mock uploads that take 0,1,2,3,..,9 ms to finish
        times(uploadCount, (index) => {
            uStore.addUpload(tag, () => {
                return new Promise((resolve) => {
                    setTimeout(resolve, index);
                });
            });
        });

        const runningTaskLog = [];
        const testCallback = (runningTaskCount) => {
            runningTaskLog.push(runningTaskCount);
        };

        return uStore.runUploads(tag, testCallback).then(() => {
            expect(uStore.tags.size).to.equal(0);

            expect(runningTaskLog).to.deep.equal(rangeRight(uploadCount));
        });
    });
});
