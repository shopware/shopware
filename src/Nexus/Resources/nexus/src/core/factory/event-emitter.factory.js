export default function EventEmitter(eventSystem) {
    let subjects = {};

    return {
        emit,
        on,
        off,
        dispose
    };

    function createName(name) {
        return `$ ${name}`;
    }

    function emit(name, args) {
        const fnName = createName(name);
        let subject = subjects[fnName];

        if (!subject) {
            subject = eventSystem.createSubject();
            subjects[fnName] = subject;
        }

        subject.next(args);

        return subject;
    }

    function on(name, handler) {
        const fnName = createName(name);
        let subject = subjects[fnName];

        if (!subject) {
            subject = eventSystem.createSubject();
            subjects[fnName] = subject;
        }

        return subject.subscribe(handler);
    }

    function off(name) {
        const fnName = createName(name);
        const subject = subjects[fnName];

        if (!subject) {
            return false;
        }

        subject.dispose();
        delete subjects[fnName];

        return true;
    }

    function dispose() {
        Object.keys(subjects).forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(subjects, key)) {
                subjects[key].dispose();
            }
        });

        subjects = {};
    }
}

