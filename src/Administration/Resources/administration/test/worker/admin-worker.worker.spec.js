import adminWorker from 'src/core/worker/admin-worker.worker';

describe('core/worker/admin-worker.worker.js', () => {
    jest.useFakeTimers();

    const MessageQueueServiceMock = function messageQueue(jestCallbackSuccess, jestCallbackFailing, errorOnIteration) {
        this.numberOfStarts = 1;
        this.stopExecution = false;

        this.consume = () => {
            return new Promise((resolve, reject) => {
                // allow to stop the consumeMessages loop
                if (this.stopExecution) {
                    return;
                }

                // create a failing response when the numberOfStarts matches the failIteration number
                if (this.numberOfStarts === errorOnIteration) {
                    setTimeout(() => {
                        // call the failing mock function with the actual number of starts
                        jestCallbackFailing(this.numberOfStarts).then(() => {
                            // eslint-disable-next-line prefer-promise-reject-errors
                            reject({ response: { status: 'Failing' } });
                            this.numberOfStarts += 1;
                        });
                    }, 30000); // simulates long polling request

                    return;
                }

                // create a success response when failIteration is not reached
                setTimeout(() => {
                    // call the success mock function with the actual number of starts
                    jestCallbackSuccess(this.numberOfStarts).then(() => {
                        resolve({ handledMessages: 20 });
                        this.numberOfStarts += 1;
                    });
                }, 30000); // simulates long polling request
            });
        };

        this.stopConsumeCall = () => {
            this.stopExecution = true;
        };

        this.startTimes = () => this.numberOfStarts;
    };

    let succeed;
    let failing;
    let messageQueueServiceMock;

    const finishPendingTimeouts = async () => {
        await succeed;
        await failing;
        jest.runOnlyPendingTimers();
    };

    beforeEach(() => {
        succeed = jest.fn(value => Promise.resolve(value));
        failing = jest.fn(value => Promise.resolve(value));

        messageQueueServiceMock = new MessageQueueServiceMock(succeed, failing, 3);
        adminWorker.consumeMessages(messageQueueServiceMock, {}, setTimeout);
    });

    it('should call the consume call once', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        messageQueueServiceMock.stopConsumeCall();

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
    });

    it('should call the consume call 2 times', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(3);
        messageQueueServiceMock.stopConsumeCall();
    });

    it('should fail at the 3rd call', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(3);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(1);

        messageQueueServiceMock.stopConsumeCall();
    });

    it('should restart at the 4th call', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(3);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(1); // first error

        await finishPendingTimeouts(); // restart timeout
        expect(messageQueueServiceMock.startTimes()).toBe(4);
        await finishPendingTimeouts(); // finish consume call
        expect(succeed).toHaveBeenCalledTimes(3);
        expect(failing).toHaveBeenCalledTimes(1);

        messageQueueServiceMock.stopConsumeCall();
    });

    it('should have success at the 5th call', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(3);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(1); // first error

        await finishPendingTimeouts(); // restart timeout
        expect(messageQueueServiceMock.startTimes()).toBe(4);
        await finishPendingTimeouts(); // finish consume call
        expect(succeed).toHaveBeenCalledTimes(3);
        expect(failing).toHaveBeenCalledTimes(1);

        expect(messageQueueServiceMock.startTimes()).toBe(5);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(4);
        expect(failing).toHaveBeenCalledTimes(1);

        messageQueueServiceMock.stopConsumeCall();
    });

    it('should have success at the 6th call', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(3);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(1); // first error

        await finishPendingTimeouts(); // restart timeout
        expect(messageQueueServiceMock.startTimes()).toBe(4);
        await finishPendingTimeouts(); // finish consume call
        expect(succeed).toHaveBeenCalledTimes(3);
        expect(failing).toHaveBeenCalledTimes(1);

        expect(messageQueueServiceMock.startTimes()).toBe(5);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(4);
        expect(failing).toHaveBeenCalledTimes(1);

        expect(messageQueueServiceMock.startTimes()).toBe(6);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(5);
        expect(failing).toHaveBeenCalledTimes(1);

        messageQueueServiceMock.stopConsumeCall();
    });

    it('should have restart after 10 seconds', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(3);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(1); // first error

        await succeed; await failing;
        jest.advanceTimersByTime(10000); // wait 10 seconds

        expect(messageQueueServiceMock.startTimes()).toBe(4);
        await finishPendingTimeouts(); // finish consume call
        expect(succeed).toHaveBeenCalledTimes(3);
        expect(failing).toHaveBeenCalledTimes(1);

        expect(messageQueueServiceMock.startTimes()).toBe(5);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(4);
        expect(failing).toHaveBeenCalledTimes(1);

        messageQueueServiceMock.stopConsumeCall();
    });

    it('should not restart before 10 seconds', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(3);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(1); // first error

        await succeed; await failing;
        jest.advanceTimersByTime(5000); // wait 5 seconds

        expect(messageQueueServiceMock.startTimes()).toBe(4);
        await finishPendingTimeouts(); // there should be no pending timeout
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(1);

        expect(messageQueueServiceMock.startTimes()).toBe(4);
        await finishPendingTimeouts();
        expect(succeed).toHaveBeenCalledTimes(3);
        expect(failing).toHaveBeenCalledTimes(1);

        messageQueueServiceMock.stopConsumeCall();
    });
});
