(function () {

const API = window.NIOS_CONFIG.dashboardStateUrl;
const COMPLETE = window.NIOS_CONFIG.actionUrl;
const KEY = window.NIOS_CONFIG.apiKey;

function getSOFromURL() {
    return new URLSearchParams(window.location.search).get('so');
}

function highlightSO() {
    const so = getSOFromURL();
    if (!so) return;

    const el = document.querySelector(`[data-so="${so}"]`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('nios-highlight');
    }
}

function renderCard(o) {

    const el = document.createElement('div');
    el.className = 'card';
    el.dataset.so = o.so_number;

    el.innerHTML = `
        <div class="so">${o.so_number}</div>
        <div>${o.customer}</div>
        <div>${o.state}</div>
        <button>DONE</button>
    `;

    el.querySelector('button').onclick = async () => {

        await fetch(COMPLETE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-NIOS-KEY': KEY
            },
            body: JSON.stringify({
                so_number: o.so_number
            })
        });

        load();
    };

    return el;
}

async function load() {

    const res = await fetch(API, {
        headers: { 'X-NIOS-KEY': KEY }
    });

    const json = await res.json();

    const grid = document.getElementById('nios-grid');
    grid.innerHTML = '';

    json.data.forEach(o => {
        grid.appendChild(renderCard(o));
    });

    highlightSO();
}

setInterval(load, 5000);
document.addEventListener('DOMContentLoaded', load);

})();