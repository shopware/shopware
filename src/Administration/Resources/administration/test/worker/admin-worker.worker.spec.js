import adminWorker from 'src/core/worker/admin-worker.worker';

describe('core/worker/admin-worker.worker.js', () => {
    jest.useFakeTimers();

    const MessageQueueServiceMock = function messageQueue(succedCb, failingCb, errorOnIteration) {
        this.numberOfStarts = 1;
        this.stopExecution = false;

        this.consume = () => {
            return new Promise((resolve, reject) => {
                if (this.stopExecution) {
                    return;
                }

                // create a failing response when the numberOfStarts succeeds failIteration
                if (this.numberOfStarts === errorOnIteration) {
                    setTimeout(() => {
                        failingCb(this.numberOfStarts).then(() => {
                            // eslint-disable-next-line prefer-promise-reject-errors
                            reject({ response: { status: 'Failing' } });
                            this.numberOfStarts += 1;
                        });
                    }, 30000);

                    return;
                }

                // create a success response when failIteration is not reached
                setTimeout(() => {
                    succedCb(this.numberOfStarts).then(() => {
                        resolve({ handledMessages: 20 });
                        this.numberOfStarts += 1;
                    });
                }, 30000);
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

    const finishConsume = async () => {
        await succeed;
        await failing;
        jest.runOnlyPendingTimers();
    };

    beforeEach(() => {
        succeed = jest.fn(value => Promise.resolve(value));
        failing = jest.fn(value => Promise.resolve(value));

        messageQueueServiceMock = new MessageQueueServiceMock(succeed, failing, 3);
        adminWorker.consumeMessages(messageQueueServiceMock, {});
    });

    it('should call the consume call once', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        messageQueueServiceMock.stopConsumeCall();

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
    });

    it('should call the consume call 2 times', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(3);
        messageQueueServiceMock.stopConsumeCall();
    });

    it('should fail at the 3rd call', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(3);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(1);

        messageQueueServiceMock.stopConsumeCall();
    });

    it('should restart at the 4th call', async () => {
        expect(succeed).toHaveBeenCalledTimes(0);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(1);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(1);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(2);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(0);

        expect(messageQueueServiceMock.startTimes()).toBe(3);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(1);

        expect(messageQueueServiceMock.startTimes()).toBe(4);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(2);
        expect(failing).toHaveBeenCalledTimes(1);

        expect(messageQueueServiceMock.startTimes()).toBe(5);
        await finishConsume();
        expect(succeed).toHaveBeenCalledTimes(3);
        expect(failing).toHaveBeenCalledTimes(1);

        messageQueueServiceMock.stopConsumeCall();
    });
});
