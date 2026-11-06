(() => {
    const screens = [...document.querySelectorAll('.screen')];
    const hudAct = document.querySelector('.hud .act');
    const hudBar = document.querySelector('.hud .bar span');
    const hudScore = document.querySelector('.hud .score b');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const groups = [...new Set([...document.querySelectorAll('[data-group]')].map((s) => s.dataset.group))];
    const points = Object.fromEntries(groups.map((group) => [group, 0]));
    const times = Object.fromEntries(groups.map((group) => [group, []]));
    let current = 0;
    let clock = null;

    function addPoint(group) {
        points[group] += 1;
        hudScore.textContent = String(Object.values(points).reduce((a, b) => a + b, 0));
        hudScore.classList.remove('pop');
        void hudScore.offsetWidth;
        hudScore.classList.add('pop');
    }

    function seconds(ms) {
        return `${Math.max(1, Math.round(ms / 1000))} s`;
    }

    function average(list) {
        const done = list.filter((t) => t !== undefined);
        return done.length ? done.reduce((a, b) => a + b, 0) / done.length : 0;
    }

    function startClock(screen) {
        clearInterval(clock);
        const label = screen.querySelector('.clock b');
        if (!label || screen.classList.contains('answered')) {
            return;
        }
        screen.startedAt = performance.now();
        label.textContent = '0';
        clock = setInterval(() => { label.textContent = String(Math.floor((performance.now() - screen.startedAt) / 1000)); }, 250);
    }

    function rank(total) {
        if (points.act1 === total) {
            return 'Act 1 without mistakes. Now imagine doing that on every agent-written PR.';
        }
        if (points.act1 === 0) {
            return 'No hits in Act 1. Without a tool, that is the normal outcome.';
        }
        return `${points.act1} of ${total} in Act 1, about what the original reviews managed.`;
    }

    function fillEnd(screen) {
        screen.querySelectorAll('[data-score]').forEach((el) => { el.textContent = String(points[el.dataset.score]); });
        screen.querySelectorAll('[data-avg]').forEach((el) => { el.textContent = seconds(average(times[el.dataset.avg])); });
        screen.querySelector('.rank').textContent = rank(Number(screen.querySelector('[data-score="act1"]').dataset.max));
    }

    function show(index) {
        current = Math.max(0, Math.min(index, screens.length - 1));
        screens.forEach((s, i) => { s.hidden = i !== current; });
        const screen = screens[current];
        hudAct.textContent = screen.dataset.act;
        hudBar.style.width = `${(current / (screens.length - 1)) * 100}%`;
        startClock(screen);
        if (screen.classList.contains('end')) {
            fillEnd(screen);
        }
        window.scrollTo(0, 0);
        screen.querySelector('[tabindex="-1"]')?.focus({ preventScroll: true });
    }

    function canLeave(screen) {
        if (screen.classList.contains('quiz')) {
            return screen.classList.contains('answered');
        }
        const next = screen.querySelector('.btn.next');
        return !next || !next.disabled;
    }

    function recordTime(screen) {
        clearInterval(clock);
        if (!screen.startedAt) {
            return;
        }
        const { group, round, compare } = screen.dataset;
        times[group][round] = performance.now() - screen.startedAt;
        if (compare) {
            screen.querySelector('.t-now').textContent = seconds(times[group][round]);
            screen.querySelector('.t-was').textContent = times[compare][round] ? seconds(times[compare][round]) : '–';
        }
    }

    function answer(screen, button) {
        if (!button || screen.classList.contains('answered')) {
            return;
        }
        const grade = button.dataset.grade;
        screen.classList.add('answered', `graded-${grade}`);
        button.classList.add('picked');
        recordTime(screen);
        screen.querySelectorAll(`.note[data-for="${button.dataset.choice}"]`).forEach((n) => { n.hidden = false; });
        const reveal = screen.querySelector('.reveal');
        reveal.hidden = false;
        if (grade === 'best') {
            addPoint(screen.dataset.group);
        }
        reveal.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
        reveal.querySelector('.btn.next').focus({ preventScroll: true });
    }

    function applyFix(button) {
        const err = button.closest('.err');
        if (err.classList.contains('fixed')) {
            return;
        }
        err.querySelectorAll('.fix-note').forEach((n) => { n.hidden = true; });
        button.nextElementSibling.hidden = false;
        if (button.dataset.grade !== 'best') {
            err.classList.add('tried');
            button.disabled = true;
            return;
        }
        err.classList.add('fixed');
        if (!err.classList.contains('tried')) {
            addPoint('plugin');
        }
        const screen = err.closest('.screen');
        const fixed = screen.querySelectorAll('.err.fixed').length;
        screen.querySelector('.fixed-count').textContent = String(fixed);
        screen.querySelector('.btn.next').disabled = fixed < screen.querySelectorAll('.err').length;
    }

    function flip(screen) {
        const toggle = screen.querySelector('.toggle');
        const on = toggle.getAttribute('aria-pressed') !== 'true';
        toggle.setAttribute('aria-pressed', String(on));
        screen.querySelector('.flag').classList.toggle('flipped', on);
        screen.querySelector('.after-flip').hidden = false;
    }

    const clickActions = {
        '.choice': (button, screen) => answer(screen, button),
        '.fix': (button) => applyFix(button),
        '.next': () => show(current + 1),
        '.toggle': (button, screen) => flip(screen),
        '.again': () => window.location.reload(),
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        const match = button && Object.keys(clickActions).find((selector) => button.matches(selector));
        if (match) {
            clickActions[match](button, button.closest('.screen'));
        }
    });

    const keyActions = {
        f: (screen) => screen.querySelector('.toggle') && flip(screen),
        arrowright: (screen) => canLeave(screen) && show(current + 1),
        enter: (screen, target) => target.matches('body, [tabindex="-1"]') && canLeave(screen) && show(current + 1),
        arrowleft: () => show(current - 1),
    };

    document.addEventListener('keydown', (event) => {
        const target = event.target instanceof Element ? event.target : document.body;
        if (event.metaKey || event.ctrlKey || event.altKey || target.matches('input, textarea')) {
            return;
        }
        const screen = screens[current];
        const key = event.key.toLowerCase();
        const choice = screen.classList.contains('quiz') && screen.querySelector(`.choice[data-key="${CSS.escape(key)}"]`);
        if (choice) {
            answer(screen, choice);
            return;
        }
        keyActions[key]?.(screen, target);
    });

    show(0);
})();
