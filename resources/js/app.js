import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm.js';

window.Alpine = Alpine;

window.chatComposer = function () {
    return {
        soonHint: '',
        soon(kind) {
            this.soonHint = kind;
            setTimeout(() => { this.soonHint = ''; }, 1800);
        },
        onDraftInput() {
            this.resize();
        },
        resize() {
            const el = this.$refs.input;
            if (! el) {
                return;
            }

            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 200) + 'px';
        },
    };
};

const scrollMessages = () => {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
};

document.addEventListener('livewire:updated', scrollMessages);
window.addEventListener('load', scrollMessages);

Livewire.start();
