export default function EventEmitter(Rx) {
    return {
        getSubject,
        createSubject,
        getObservable,
        createObservable
    };

    function getSubject() {
        return Rx.Subject;
    }

    /**
     * Returns a new instance of the event subject object.
     * The objects needs to implement the following methods:
     * - next()
     * - dispose()
     * - subscribe()
     * - scan()
     *
     * @param args {Mixed=}
     * @returns {Rx.Subject}
     */
    function createSubject() {
        return new Rx.Subject();
    }

    function getObservable() {
        return Rx.Observable;
    }

    /**
     * Returns a new instance the event observable object.
     * It defines an object which can be used for an observer
     * @param args {Mixed=}
     * @returns {Rx.Observable}
     */
    function createObservable() {
        return new Rx.Observable();
    }
}
