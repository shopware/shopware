import Plugin from '../../helper/plugin.helper';

export default class SimplePlugin extends Plugin {
    constructor(el, config, name = 'simplePlugin') {
        super(name);

        this.el = el;
        this.opts = this.getConfig(config, {
            delay: 300
        });

        this.init();
    }

    init() {
        const el = this.render(this.h('div', {
            attrs: {
                id: 'foobar',
                class: 'super cool guy',
                style: 'user-select: none'
            },
            listeners: {
                click: (event) => {
                    event.preventDefault();
                    const target = event.target;
                    target.style.color = this.getRandomColor();
                }
            },
            children: [
                this.h('span', {
                    attrs: {
                        class: 'text-element'
                    },
                    children: [
                        this.h('strong', {
                            text: 'Foobar'
                        })
                    ]
                })
            ]
        }));

        this.el.appendChild(el);
    }

    getRandomColor() {
        const range = '0123456789ABCDEF';
        let color = '#';
        for (let i = 0; i < 6; i += 1) {
            color += range[Math.floor(Math.random() * 16)];
        }
        return color;
    }
}
