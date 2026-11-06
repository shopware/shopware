(() => {
    const screens = [...document.querySelectorAll('.screen')];
    const rounds = screens.filter((s) => s.classList.contains('round'));
    const hudAct = document.querySelector('.hud .act');
    const hudBar = document.querySelector('.hud .bar span');
    const hudScore = document.querySelector('.hud .score b');
    const ranks = {
        none: 'None this time. Without a tool, that is the normal outcome.',
        some: 'Some right, some wrong: about what the original reviews managed.',
        close: 'Close. One wrong call means a plugin bug report or a PR blocked for nothing.',
        all: 'Perfect. Now imagine doing that on every agent-written PR, or let the check do it.',
    };

    function rank() {
        if (score === rounds.length) {
            return ranks.all;
        }
        if (score === rounds.length - 1) {
            return ranks.close;
        }
        return score === 0 ? ranks.none : ranks.some;
    }
    let current = 0;
    let score = 0;

    function setScore(value) {
        score = value;
        hudScore.textContent = String(score);
        hudScore.classList.remove('pop');
        void hudScore.offsetWidth;
        hudScore.classList.add('pop');
    }

    function show(index) {
        current = Math.max(0, Math.min(index, screens.length - 1));
        screens.forEach((s, i) => { s.hidden = i !== current; });
        const screen = screens[current];
        hudAct.textContent = screen.dataset.act;
        hudBar.style.width = `${(current / (screens.length - 1)) * 100}%`;
        if (screen.classList.contains('end')) {
            screen.querySelector('.fs').textContent = String(score);
            screen.querySelector('.rank').textContent = rank();
        }
        window.scrollTo(0, 0);
        screen.querySelector('[tabindex="-1"]')?.focus({ preventScroll: true });
    }

    function canLeave(screen) {
        if (screen.classList.contains('round')) {
            return screen.classList.contains('answered');
        }
        const next = screen.querySelector('.btn.next');
        return !next || !next.disabled;
    }

    function answer(screen, choice) {
        if (screen.classList.contains('answered')) {
            return;
        }
        const right = choice === screen.dataset.answer;
        screen.classList.add('answered', right ? 'right' : 'wrong');
        screen.querySelector(`[data-choice="${choice}"]`).classList.add('picked');
        const reveal = screen.querySelector('.reveal');
        reveal.hidden = false;
        if (right) {
            setScore(score + 1);
        }
        reveal.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        reveal.querySelector('.btn.next').focus({ preventScroll: true });
    }

    function flip(screen) {
        const toggle = screen.querySelector('.toggle');
        const on = toggle.getAttribute('aria-pressed') !== 'true';
        toggle.setAttribute('aria-pressed', String(on));
        screen.querySelector('.flag').classList.toggle('flipped', on);
        screen.querySelector('.after-flip').hidden = false;
    }

    function reset() {
        rounds.forEach((s) => {
            s.classList.remove('answered', 'right', 'wrong');
            s.querySelectorAll('.picked').forEach((b) => b.classList.remove('picked'));
            s.querySelector('.reveal').hidden = true;
        });
        score = 0;
        hudScore.textContent = '0';
        show(1);
    }

    document.addEventListener('click', (event) => {
        const target = event.target.closest('button');
        if (!target) {
            return;
        }
        const screen = target.closest('.screen');
        if (target.matches('.choice')) {
            answer(screen, target.dataset.choice);
        } else if (target.matches('.next')) {
            show(current + 1);
        } else if (target.matches('.toggle')) {
            flip(screen);
        } else if (target.matches('.again')) {
            reset();
        }
    });

    document.querySelectorAll('.roles').forEach((roles) => {
        const screen = roles.closest('.screen');
        const all = roles.querySelectorAll('.role');
        all.forEach((role) => {
            role.addEventListener('toggle', () => {
                if (role.open) {
                    role.classList.add('seen');
                }
                const opened = roles.querySelectorAll('.role.seen').length;
                screen.querySelector('.opened').textContent = String(opened);
                screen.querySelector('.btn.next').disabled = opened < all.length;
            });
        });
    });

    const keyActions = {
        m: (screen) => screen.classList.contains('round') && answer(screen, 'merge'),
        b: (screen) => screen.classList.contains('round') && answer(screen, 'block'),
        f: (screen) => screen.querySelector('.toggle') && flip(screen),
        arrowright: (screen) => canLeave(screen) && show(current + 1),
        enter: (screen, target) => target.matches('body, [tabindex="-1"]') && canLeave(screen) && show(current + 1),
        arrowleft: () => show(current - 1),
    };

    document.addEventListener('keydown', (event) => {
        const target = event.target instanceof Element ? event.target : document.body;
        const action = keyActions[event.key.toLowerCase()];
        if (!action || event.metaKey || event.ctrlKey || event.altKey || target.matches('input, textarea')) {
            return;
        }
        action(screens[current], target);
    });

    show(0);
})();
