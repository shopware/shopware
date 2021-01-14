import adminWorker from 'src/core/worker/admin-worker.worker';

describe('core/worker/admin-worker.worker.js', () => {
    jest.useFakeTimers();

    const MessageQueueServiceMock = function messageQueue(options) {
        const {
            jestCallbackSuccess,
            jestCallbackFailing,
            errorOnIteration = 0,
            initialHandledMessages = 20,
            reduceHandledMessages = 0
        } = options;

        this.numberOfStarts = 1;
        this.stopExecution = false;
        this.handledMessages = initialHandledMessages;

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

                // create a success response directly (1 second) when no handledMessages exists
                if (this.handledMessages <= 0) {
                    setTimeout(() => {
                        // call the success mock function with the actual number of starts
                        jestCallbackSuccess(this.numberOfStarts).then(() => {
                            resolve({ handledMessages: this.handledMessages });
                            this.numberOfStarts += 1;

                            if (this.handledMessages > 0) {
                                this.handledMessages -= reduceHandledMessages;
                            }
                        });
                    }, 1000); // simulates 1 second request

                    return;
                }

                // create a success response when failIteration is not reached
                setTimeout(() => {
                    // call the success mock function with the actual number of starts
                    jestCallbackSuccess(this.numberOfStarts).then(() => {
                        resolve({ handledMessages: this.handledMessages });
                        this.numberOfStarts += 1;

                        if (this.handledMessages > 0) {
                            this.handledMessages -= reduceHandledMessages;
                        }
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
        // wait virtually 30 seconds
        await jest.runTimersToTime(30000);
    };

    beforeEach(() => {
        jest.clearAllTimers();
        succeed = jest.fn(value => Promise.resolve(value));
        failing = jest.fn(value => Promise.resolve(value));

        messageQueueServiceMock = new MessageQueueServiceMock({
            jestCallbackSuccess: succeed,
            jestCallbackFailing: failing,
            errorOnIteration: 3,
            initialHandledMessages: 20,
            reduceHandledMessages: 0
        });
        adminWorker.consumeMessages(messageQueueServiceMock, {}, setTimeout);
    });

    it('should call the consume call once (has messages)', async () => {
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

    it('should call the consume call 2 times (has messages)', async () => {
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

    it('should fail at the 3rd call (has messages)', async () => {
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

    it('should restart at the 4th call (has messages)', async () => {
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

    it('should have success at the 5th call (has messages)', async () => {
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

    it('should have success at the 6th call (has messages)', async () => {
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

    it('should have restart after 10 seconds (has messages)', async () => {
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

    it('should not restart before 10 seconds (has messages)', async () => {
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

    it('should get a response directly after a second (no messages)', async () => {
        // prepare message queue mock without handled messages
        succeed = jest.fn(value => Promise.resolve(value));
        failing = jest.fn(value => Promise.resolve(value));

        messageQueueServiceMock = new MessageQueueServiceMock({
            jestCallbackSuccess: succeed,
            jestCallbackFailing: failing,
            errorOnIteration: 3,
            initialHandledMessages: 0,
            reduceHandledMessages: 0
        });
        adminWorker.consumeMessages(messageQueueServiceMock, {}, setTimeout);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        await jest.runTimersToTime(1000);
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);
    });

    it('should send request again after 20 seconds (no messages)', async () => {
        // prepare message queue mock without handled messages
        succeed = jest.fn(value => Promise.resolve(value));
        failing = jest.fn(value => Promise.resolve(value));

        messageQueueServiceMock = new MessageQueueServiceMock({
            jestCallbackSuccess: succeed,
            jestCallbackFailing: failing,
            errorOnIteration: 3,
            initialHandledMessages: 0,
            reduceHandledMessages: 0
        });
        adminWorker.consumeMessages(messageQueueServiceMock, {}, setTimeout);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        await jest.runTimersToTime(1000); // wait for request response
        expect(messageQueueServiceMock.startTimes()).toBe(2);
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        await jest.runTimersToTime(0); // wait for succeed callback
        await jest.runTimersToTime(20000); // wait 20 seconds for next request
        await jest.runTimersToTime(1000); // wait for request response

        // the next request should be finished
        expect(messageQueueServiceMock.startTimes()).toBe(3);
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);
    });

    it('should not send request before 20 seconds (no messages)', async () => {
        // prepare message queue mock without handled messages
        succeed = jest.fn(value => Promise.resolve(value));
        failing = jest.fn(value => Promise.resolve(value));

        messageQueueServiceMock = new MessageQueueServiceMock({
            jestCallbackSuccess: succeed,
            jestCallbackFailing: failing,
            errorOnIteration: 3,
            initialHandledMessages: 0,
            reduceHandledMessages: 0
        });
        adminWorker.consumeMessages(messageQueueServiceMock, {}, setTimeout);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        await jest.runTimersToTime(1000); // wait for request response
        expect(messageQueueServiceMock.startTimes()).toBe(2);
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        await jest.runTimersToTime(0); // wait for succeed callback
        await jest.runTimersToTime(19000); // wait 19 seconds for next request
        await jest.runTimersToTime(1000); // wait for request response

        // the next request should not been called because we only wait 19 seconds
        expect(messageQueueServiceMock.startTimes()).toBe(2);
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        await jest.runTimersToTime(500); // wait another 500ms
        expect(messageQueueServiceMock.startTimes()).toBe(2);

        await jest.runTimersToTime(500); // wait another 500ms
        expect(messageQueueServiceMock.startTimes()).toBe(3);
    });
});
