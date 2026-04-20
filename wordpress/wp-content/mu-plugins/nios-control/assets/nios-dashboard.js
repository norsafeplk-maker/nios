(function () {
"use strict";

if (!window.NIOS_CONFIG) {
    console.error("NIOS_CONFIG missing");
    document.addEventListener("DOMContentLoaded", function () {
        const err = document.getElementById("nios-error");
        if (err) {
            err.classList.remove("hidden");
            err.textContent = "System config missing (NIOS_CONFIG)";
        }
    });
    return;
}

const API = window.NIOS_CONFIG.dashboardStateUrl;
const COMPLETE = window.NIOS_CONFIG.actionUrl;
const KEY = window.NIOS_CONFIG.apiKey;
const REFRESH = window.NIOS_CONFIG.refreshMs || 5000;

function getSOFromURL() {
    return new URLSearchParams(window.location.search).get("so");
}

function highlightSO() {
    const so = getSOFromURL();
    if (!so) return;

    const el = document.querySelector(`[data-so="${so}"]`);
    if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "center" });
        el.classList.add("nios-highlight");
    }
}

function setStatus(text) {
    const el = document.getElementById("nios-last-updated");
    if (el) el.textContent = text;
}

function showError(msg) {
    const el = document.getElementById("nios-error");
    if (!el) return;
    el.classList.remove("hidden");
    el.textContent = msg;
}

function clearError() {
    const el = document.getElementById("nios-error");
    if (!el) return;
    el.classList.add("hidden");
    el.textContent = "";
}

function renderCard(o) {
    const el = document.createElement("div");
    el.className = "card";
    el.dataset.so = o.so_number || "";

    const stateText = o.substate || o.state || "";

    el.innerHTML = `
        <div class="so">${o.so_number || ""}</div>
        <div class="meta">${o.customer || ""}</div>
        <div class="badge">${stateText}</div>
        <button type="button">DONE</button>
    `;

    const btn = el.querySelector("button");
    btn.addEventListener("click", async function () {
        btn.disabled = true;
        btn.textContent = "WORKING...";

        try {
            const res = await fetch(COMPLETE, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-NIOS-KEY": KEY
                },
                body: JSON.stringify({
                    so_number: o.so_number
                })
            });

            if (!res.ok) {
                throw new Error("Action API error: " + res.status);
            }

            clearError();
            await load();
        } catch (err) {
            console.error(err);
            showError("Action failed");
        } finally {
            btn.disabled = false;
            btn.textContent = "DONE";
        }
    });

    return el;
}

async function load() {
    try {
        const res = await fetch(API, {
            method: "GET",
            headers: {
                "X-NIOS-KEY": KEY
            }
        });

        if (!res.ok) {
            throw new Error("API error: " + res.status);
        }

        const json = await res.json();
        const grid = document.getElementById("nios-grid");
        if (!grid) return;

        grid.innerHTML = "";

        const data = Array.isArray(json.data) ? json.data : [];

        if (data.length === 0) {
            grid.innerHTML = `<div class="nios-empty">No orders</div>`;
        } else {
            data.forEach(function (o) {
                grid.appendChild(renderCard(o));
            });
        }

        clearError();
        highlightSO();
        setStatus("Updated: " + new Date().toLocaleTimeString());
    } catch (err) {
        console.error(err);
        showError("Failed to load dashboard");
    }
}

document.addEventListener("DOMContentLoaded", function () {
    load();
    setInterval(load, REFRESH);
});

})();