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

let mermaidLoader;
const diagramsInFlight = new WeakSet();

function loadMermaid() {
    mermaidLoader ??= import('mermaid').then(({ default: mermaid }) => {
        mermaid.initialize({
            startOnLoad: false,
            securityLevel: 'strict',
            theme: 'neutral',
            fontFamily: 'Figtree, ui-sans-serif, system-ui, sans-serif',
        });

        return mermaid;
    });

    return mermaidLoader;
}

async function renderDiscoveryDiagrams(root = document) {
    if (! root || typeof root.querySelectorAll !== 'function') {
        return;
    }

    const nodes = [...root.querySelectorAll('.discovery-markdown pre.mermaid')]
        .filter((node) => ! node.hasAttribute('data-processed') && ! diagramsInFlight.has(node));

    if (nodes.length === 0) {
        return;
    }

    nodes.forEach((node) => diagramsInFlight.add(node));

    try {
        const mermaid = await loadMermaid();
        const pending = nodes.filter((node) => node.isConnected && ! node.hasAttribute('data-processed'));
        if (pending.length === 0) {
            return;
        }

        await mermaid.run({ nodes: pending, suppressErrors: true });
    } finally {
        nodes.forEach((node) => diagramsInFlight.delete(node));
    }
}

Livewire.hook('morphed', ({ el }) => {
    renderDiscoveryDiagrams(el);
});

document.addEventListener('livewire:initialized', () => {
    renderDiscoveryDiagrams();
});

Livewire.start();
