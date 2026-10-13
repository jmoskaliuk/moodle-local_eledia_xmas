// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Canvas snowfall with local drifts and snow-catching Moodle elements.
 *
 * @module local_eledia_xmas/snow
 * @copyright 2026 eLeDia GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init as initCelebrations} from 'local_eledia_xmas/celebrations';
import {init as initSurprises} from 'local_eledia_xmas/surprises';

/**
 * Initialise the seasonal overlay once per page.
 * @param {Object} settings Validated server configuration.
 */
export const init = settings => {
    const root = document.getElementById('local-eledia-xmas');

    if (!root || root.dataset.initialized) {
        return;
    }

    root.dataset.initialized = 'true';
    // Strings, audio and completion states come from the overlay markup to keep the AMD call small.
    const config = {...settings, ...JSON.parse(root.dataset.pagedata || '{}')};
    const celebrations = initCelebrations(root, config);
    const surprises = initSurprises(root, config, celebrations);
    const canvas = root.querySelector('[data-region="snow"]');
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        root.remove();
        return;
    }

    const toggle = root.querySelector('[data-action="toggle"]');
    const fast = root.querySelector('[data-action="fast"]') || {
        checked: false
    };
    const cover = root.querySelector('[data-action="cover"]') || {
        checked: config.fullcover
    };
    cover.checked = config.fullcover;
    const preview = root.querySelector('[data-action="preview"]');
    const status = root.querySelector('[data-region="status"]');
    const snowHelp = root.querySelector('[data-region="snow-help"]');
    const snowHelpTitle = root.querySelector('[data-region="snow-help-title"]');
    const snowHelpVariants = ['snowhelpbrrr', 'snowhelpblanket', 'snowhelpwinter'];
    let snowHelpVariant = Math.floor(Math.random() * snowHelpVariants.length);
    const pronounced = config.contrast === 'strong';
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    let enabled = !motion.matches;

    try {
        enabled = !motion.matches && localStorage.getItem(config.storagekey) !== 'false';
    } catch (_) {
        /* Storage can be unavailable. */
    }

    let width = 0,
            height = 0,
            flakes = [],
            terrain = [];
    let lastActivity = performance.now(),
            previous = 0,
            frame = 0;
    let snowDepth = 0,
            clearing = null,
            forcePause = false,
            pauseSeconds = 0;
    let uiUpdated = 0,
            storm = 0;

    const baseCount = () => Math.min(180, Math.max(60, Math.round(width / 9 * config.intensity)));

    const spacing = 12;

    const random = (min, max) => min + Math.random() * (max - min);

    const idleDelay = () => fast.checked ? 5000 : config.idledelay;
        // Wind deposits more snow in a few broad, unevenly spaced drifts.

    const driftCenters = [0.08, 0.36, 0.73, 0.96];
    /**
     * Choose a landing corridor influenced by wind.
     */
    function snowfallX() {
        if (Math.random() < 0.3) {
            return random(0, width);
        }

        const center = driftCenters[Math.floor(Math.random() * driftCenters.length)];
        const gaussian = Math.sqrt(-2 * Math.log(Math.max(0.0001, Math.random()))) * Math.cos(Math.random() * Math.PI * 2);
        return ((center + gaussian * 0.045) * width + width) % width;
    }

    const makeFlake = (spread = false) => ({
        x: snowfallX(),
        y: spread ? random(-height, height) : random(-80, -8),
        radius: random(1.3, 3.4),
        speed: random(24, 65),
        phase: random(0, Math.PI * 2),
        opacity: random(0.45, 0.95)
    });

    let ledges = [];
    let ledgesDirty = true;
    const imageSelector = '[data-xmas-image], .dashboard-card .card-img-top, .course-card .card-img-top, '
        + '.coursebox .courseimage img, #courseheaderimage';
    const selector = '[data-xmas-ledge], .dashboard-card, .coursebox, .activityiconcontainer';

    const refreshLedges = () => {
        if (!config.ledges) {
            return;
        }

        const existing = new Map(ledges.map(ledge => [ledge.element, ledge]));
        // An image replaces its card's top ledge, so flakes can reach the bottom of the picture.
        const images = Array.from(document.querySelectorAll(imageSelector));
        const surfaces = Array.from(document.querySelectorAll(selector)).filter(element =>
            !element.parentElement.closest(selector) && !images.some(image => element.contains(image)));
        ledges = [...images, ...surfaces].slice(0, 150).map(element => {
            const image = element.matches(imageSelector);
            const icon = element.matches('[data-xmas-icon], .activityiconcontainer');
            const style = getComputedStyle(element);
            return existing.get(element) || {
                element,
                image,
                profile: new Float64Array(icon ? 13 : 65),
                limit: icon ? 17 : 27,
                radii: [style.borderTopLeftRadius, style.borderTopRightRadius,
                    style.borderBottomRightRadius, style.borderBottomLeftRadius].map(value => parseFloat(value) || 0),
                rect: null,
                base: 0
            };
        });
        ledgesDirty = false;
    };
    /**
     * Capture snow caps before clearing.
     */
    function snapshotCaps() {
        return ledges.map(ledge => Array.from(ledge.profile));
    }
    /**
     * Read current positions of supported visible surfaces.
     */
    function measureLedges() {
        if (ledgesDirty && !clearing) {
            refreshLedges();
        }

        for (const ledge of ledges) {
            ledge.rect = ledge.element.getBoundingClientRect();
            ledge.base = ledge.image ? ledge.rect.bottom : ledge.rect.top;
            if (ledge.image) {
                ledge.limit = Math.min(56, ledge.rect.height * 0.24);
                // Keep existing snow inside the image after a responsive layout change.
                ledge.profile = ledge.profile.map(value => Math.min(value, ledge.limit));
            }
        }

        return ledges.filter(({rect, base, limit, element}) =>
            rect.width > 0 && rect.height > 0 && base > 0 && base - limit < height
            && getComputedStyle(element).visibility === 'visible').sort((a, b) => a.base - b.base);
    }
    /**
     * Interpolate the accumulated snow height on an element.
     * @param {Object} ledge ledge value.
     * @param {number} position position value.
     */
    function capHeight(ledge, position) {
        const index = position * (ledge.profile.length - 1);
        const left = Math.floor(index),
                    part = index - left;
        return ledge.profile[left] * (1 - part) + ledge.profile[Math.min(left + 1, ledge.profile.length - 1)] * part;
    }
    /**
     * Deposit a crossing flake on a surface with remaining capacity.
     * @param {Object} flake flake value.
     * @param {number} oldY oldY value.
     * @param {Array} visibleLedges visibleLedges value.
     */
    function catchOnLedge(flake, oldY, visibleLedges) {
        for (const ledge of visibleLedges) {
            const {
                rect,
                profile,
                limit
            } = ledge;

            if (flake.x < rect.left || flake.x > rect.right) {
                continue;
            }

            const position = (flake.x - rect.left) / rect.width;
            const top = ledge.base - capHeight(ledge, position);

            if (oldY + flake.radius > top || flake.y + flake.radius < top || surface(flake.x) <= top) {
                continue;
            }

            const center = Math.round(position * (profile.length - 1));
        // Rounded edges and irregular caps; saturated spots let later flakes fall through.

            const ceiling = i => limit * Math.pow(Math.sin(Math.PI * i / (profile.length - 1)), 0.45)
                * (0.88 + 0.12 * Math.sin(i * 0.7));

            if (profile[center] >= ceiling(center) * 0.96) {
                continue;
            }

            for (let i = Math.max(0, center - 3); i <= Math.min(profile.length - 1, center + 3); i++) {
                profile[i] = Math.min(ceiling(i), profile[i] + flake.radius * 0.65 * Math.exp(-((i - center) ** 2) / 4));
            }

            Object.assign(flake, makeFlake());
            return true;
        }

        return false;
    }
    /**
     * Paint snow caps in viewport coordinates.
     * @param {Array} visibleLedges visibleLedges value.
     */
    function drawCaps(visibleLedges) {
        for (const {
            rect,
            profile,
            limit,
            base,
            image,
            radii
        } of visibleLedges) {
            if (!profile.some(value => value > 0.05)) {
                continue;
            }

            ctx.save();
            if (image) {
                ctx.beginPath();
                ctx.roundRect(rect.left, rect.top, rect.width, rect.height, radii);
                ctx.clip();
            }
            ctx.beginPath();
            ctx.moveTo(rect.left, base + 1);

            for (let i = 0; i < profile.length - 1; i++) {
                const x = rect.left + rect.width * i / (profile.length - 1);
                const nextX = rect.left + rect.width * (i + 1) / (profile.length - 1);
                ctx.quadraticCurveTo(x, base - profile[i], (x + nextX) / 2, base - (profile[i] + profile[i + 1]) / 2);
            }

            ctx.lineTo(rect.right, base + 1);
            ctx.closePath();
            const gradient = ctx.createLinearGradient(0, base - limit, 0, base + 2);
            gradient.addColorStop(0, '#fff');
            gradient.addColorStop(pronounced ? 0.35 : 0.7, '#fff');
            gradient.addColorStop(1, pronounced ? '#87a7bb' : '#dce9ef');
            ctx.fillStyle = gradient;
            ctx.shadowColor = pronounced ? '#25465b99' : '#45647535';
            ctx.shadowBlur = pronounced ? 8 : 5;
            ctx.shadowOffsetY = 2;
            ctx.fill();
            if (pronounced) {
                ctx.strokeStyle = '#6e91a8';
                ctx.lineWidth = 0.8;
                ctx.stroke();
            }
            ctx.shadowBlur = 0;
            ctx.shadowOffsetY = 0;
            ctx.restore();
        }
    }
    /**
     * Resize the canvas and resample existing snowbanks.
     */
    function resize() {
        const oldHeight = height;
        const oldTerrain = terrain.slice();
        width = innerWidth;
        height = innerHeight;
        const ratio = Math.min(devicePixelRatio || 1, 2);
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        const count = Math.ceil(width / spacing) + 2;
        terrain = Array.from({
            length: count
        }, (_, i) => {
            if (!oldTerrain.length) {
                return 0;
            }

            const position = i / (count - 1) * (oldTerrain.length - 1);
            const left = Math.floor(position),
                        fraction = position - left;
            return (oldTerrain[left] * (1 - fraction)
                + oldTerrain[Math.min(left + 1, oldTerrain.length - 1)] * fraction) * height / oldHeight;
        });
        snowDepth = Math.max(0, ...terrain);

        if (clearing) {
            clearing = {
                start: performance.now(),
                terrain: terrain.slice(),
                caps: snapshotCaps()
            };
        }

        flakes = Array.from({
            length: baseCount()
        }, () => makeFlake(true));
    }
    /**
     * Interpolate the floor snow height.
     * @param {number} x x value.
     */
    function surface(x) {
        const index = Math.max(0, Math.min(terrain.length - 2, Math.floor(x / spacing)));
        const fraction = Math.max(0, Math.min(1, x / spacing - index));
        const bump = terrain[index] * (1 - fraction) + terrain[index + 1] * fraction;
        return height - bump;
    }
    /**
     * Clear accumulation after an input without consuming the event.
     */
    function activity() {
        snowHelp.hidden = true;
        lastActivity = performance.now();
        forcePause = false;
        pauseSeconds = 0;

        if (!clearing && (snowDepth > 0 || ledges.some(ledge => ledge.profile.some(value => value > 0)))) {
            clearing = {
                start: lastActivity,
                terrain: terrain.slice(),
                caps: snapshotCaps()
            };
        }
    }
    /**
     * Update translated controls when the effect state changes.
     * @param {boolean} idle idle value.
     */
    function updateUi(idle) {
        surprises.update(pauseSeconds, enabled, fast.checked);
        // Use the lowest drift: a single high snowbank does not mean the page is covered.
        const covered = enabled && idle && cover.checked && height > 0
            && terrain.length > 0 && Math.min(...terrain) >= height * 0.9;
        if (covered && snowHelp.hidden) {
            snowHelpTitle.textContent = config.strings[snowHelpVariants[snowHelpVariant]];
            snowHelpVariant = (snowHelpVariant + 1) % snowHelpVariants.length;
        }
        snowHelp.hidden = !covered;
        let label = config.strings.falling;
        if (!enabled) {
            label = motion.matches ? config.strings.reducedmotion : config.strings.quiet;
        } else if (clearing) {
            label = config.strings.clearing;
        } else if (idle) {
            label = storm > 0.75 ? config.strings.storm : config.strings.building;
        }
        if (status.textContent !== label) {
            status.textContent = label;
        }

        const actionLabel = enabled ? config.strings.snowoff : config.strings.snowon;
        toggle.setAttribute('aria-label', actionLabel);
        toggle.dataset.tooltip = actionLabel;
        toggle.setAttribute('aria-pressed', String(enabled));

        if (preview) {
            preview.disabled = !enabled;
        }
    }
    /**
     * Move visible flakes, resolve collisions and paint them.
     * @param {number} now Animation timestamp.
     * @param {number} dt Elapsed seconds.
     * @param {boolean} idle Whether accumulation is active.
     * @param {Array} visibleLedges Visible snow-catching elements.
     * @param {boolean} canDeposit Whether floor accumulation is allowed.
     * @param {Float64Array} deposits Frame-local floor deposits.
     */
    function drawFlakes(now, dt, idle, visibleLedges, canDeposit, deposits) {
        for (const flake of flakes) {
            const oldY = flake.y;
            flake.y += flake.speed * dt * (1 + storm * 6);
            flake.x += (Math.sin(now / 1200 + flake.phase) * (12 + storm * 35) + 5 + storm * 40) * dt;

            if (flake.x > width + 5) {
                flake.x = -5;
            }

            if (flake.x < -5) {
                flake.x = width + 5;
            }

            if (idle && catchOnLedge(flake, oldY, visibleLedges)) {
                continue;
            }

            if (flake.y + flake.radius >= (snowDepth > 0 ? surface(flake.x) : height + 8)) {
                if (canDeposit) {
                    // Add volume only around this landing point, never to the whole bank.
                    const center = Math.max(0, Math.min(terrain.length - 1, Math.round(flake.x / spacing)));
                    const start = Math.max(0, center - 4),
                                end = Math.min(terrain.length - 1, center + 4);
                    let totalWeight = 0;

                    for (let i = start; i <= end; i++) {
                        totalWeight += Math.exp(-((i - center) ** 2) / 5);
                    }

                    const volume = flake.radius * flake.radius * 22 / spacing;

                    for (let i = start; i <= end; i++) {
                        deposits[i] += volume * Math.exp(-((i - center) ** 2) / 5) / totalWeight;
                    }
                }

                Object.assign(flake, makeFlake());
            }

            ctx.beginPath();
            ctx.arc(flake.x, flake.y, flake.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255,255,255,${flake.opacity})`;
            ctx.fill();
        }

    }
    /**
     * Settle locally deposited snow while preserving the shape of broad drifts.
     * @param {Float64Array} deposits Local deposits.
     * @param {number} dt Elapsed seconds.
     */
    function settleSnow(deposits, dt) {
        const peakDeposit = Math.max(0, ...deposits);
        const scale = peakDeposit ? Math.min(1, height * 0.2 * dt / peakDeposit) : 1;

        for (let i = 0; i < terrain.length; i++) {
            terrain[i] += deposits[i] * scale;
        } // Excess snow slips down steep slopes; volume is conserved.

        for (let pass = 0; pass < 12; pass++) {
            for (let j = 0; j < terrain.length - 1; j++) {
                const i = pass % 2 ? terrain.length - 2 - j : j;
                const difference = terrain[i] - terrain[i + 1];
                const excess = Math.max(0, Math.abs(difference) - spacing * 0.85);
                const transfer = Math.sign(difference) * excess * 0.45;
                terrain[i] -= transfer;
                terrain[i + 1] += transfer;
            }
        } // Gentle settling rounds crests without flattening the broad drifts.

        const settled = terrain.slice();

        for (let i = 0; i < terrain.length - 1; i++) {
            const flow = (terrain[i] - terrain[i + 1]) * Math.min(0.2, dt * 5);
            settled[i] -= flow;
            settled[i + 1] += flow;
        }

        terrain = settled;
        snowDepth = Math.max(0, ...terrain);
    }
    /**
     * Paint the settled snowbanks using the configured contrast.
     */
    function drawBank() {
        if (snowDepth > 0.1) {
            ctx.beginPath();
            ctx.moveTo(0, height);
            ctx.lineTo(0, surface(0));

            for (let x = 0; x <= width; x += spacing) {
                const next = Math.min(width, x + spacing);
                ctx.quadraticCurveTo(x, surface(x), (x + next) / 2, (surface(x) + surface(next)) / 2);
            }

            ctx.lineTo(width, surface(width));
            ctx.lineTo(width, height);
            ctx.closePath();
            const gradient = ctx.createLinearGradient(0, height - snowDepth - 40, 0, height);
            gradient.addColorStop(0, '#ffffff');
            gradient.addColorStop(1, pronounced ? '#adc5d4' : '#e6eff3');
            ctx.fillStyle = gradient;
            ctx.shadowColor = pronounced ? '#325b7790' : '#54778022';
            ctx.shadowBlur = 15;
            ctx.shadowOffsetY = -4;
            ctx.fill();
            ctx.shadowBlur = 0;
            ctx.shadowOffsetY = 0;
        }
    }
    /**
     * Advance the simulation using elapsed time.
     * @param {number} now now value.
     */
    function draw(now) {
        const dt = Math.min((now - previous) / 1000 || 0, 0.05);
        previous = now;
        const idle = enabled && !clearing && (forcePause || now - lastActivity >= idleDelay());

        if (clearing) {
            const progress = Math.min(1, (now - clearing.start) / 900);
            terrain = clearing.terrain.map(value => value * Math.pow(1 - progress, 3));
            ledges.forEach((ledge, index) => {
                ledge.profile = Float64Array.from(clearing.caps[index], value => value * Math.pow(1 - progress, 3));
            });
            snowDepth = Math.max(0, ...terrain);

            if (progress >= 1) {
                clearing = null;
                snowDepth = 0;
                terrain.fill(0);
            }
        } else if (idle) {
            pauseSeconds += dt;
        }

        const targetStorm = idle ? Math.min(1, pauseSeconds / (fast.checked ? 5 : 18)) : 0;
        storm += (targetStorm - storm) * Math.min(1, dt * (idle ? 2 : 5));
        const targetCount = Math.min(1600, Math.round(baseCount() * (1 + storm * 9)));
        // New flakes enter from above, so the storm visibly arrives before the bank grows.

        while (flakes.length < targetCount) {
            flakes.push(makeFlake());
        }

        if (flakes.length > targetCount) {
            flakes.length = targetCount;
        }

        const deposits = new Float64Array(terrain.length);
        const maximum = cover.checked ? height + width : Math.min(130, height * 0.17);
        const canDeposit = idle && snowDepth < maximum && Math.min(...terrain) < height + 10;
        const visibleLedges = measureLedges();
        ctx.clearRect(0, 0, width, height);

        if (enabled) {
            drawFlakes(now, dt, idle, visibleLedges, canDeposit, deposits);

            if (canDeposit) {
                settleSnow(deposits, dt);
            }

            surprises.position();
            drawCaps(visibleLedges);

            drawBank();
        }

        if (now - uiUpdated > 200) {
            updateUi(idle);
            uiUpdated = now;
        }

        if (enabled && !document.hidden) {
            frame = requestAnimationFrame(draw);
        } else {
            frame = 0;
        }
    }
    /**
     * Resume animation when the document is visible.
     */
    function resume() {
        if (enabled && !document.hidden && !frame) {
            previous = performance.now();
            frame = requestAnimationFrame(draw);
        }
    }
    /**
     * Apply and reset the local effect state.
     * @param {boolean} value value value.
     */
    function setEnabled(value) {
        enabled = value;
        celebrations.setEnabled(value);
        activity();
        snowDepth = 0;
        clearing = null;
        storm = 0;
        terrain.fill(0);
        ledges.forEach(ledge => ledge.profile.fill(0));
        flakes = Array.from({
            length: baseCount()
        }, () => makeFlake(true));
        ctx.clearRect(0, 0, width, height);

        if (!enabled) {
            cancelAnimationFrame(frame);
            frame = 0;
        }

        updateUi(false);
        resume();
    }

    for (const event of ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart', 'scroll']) {
        window.addEventListener(event, activity, {
            passive: true
        });
    }

    toggle.addEventListener('click', () => {
        setEnabled(!enabled);

        try {
            localStorage.setItem(config.storagekey, String(enabled));
        } catch (_) {
            /* Optional persistence. */
        }
    });

    if (preview) {
        preview.addEventListener('click', () => {
            if (!enabled) {
                return;
            }

            forcePause = true;
            clearing = null;
            pauseSeconds = 0;
            updateUi(true);
        });
        fast.addEventListener('change', activity);
        cover.addEventListener('change', activity);
    }

    motion.addEventListener('change', () => {
        if (motion.matches) {
            setEnabled(false);
        }
    });
    document.addEventListener('visibilitychange', () => {
        surprises.pause();
        cancelAnimationFrame(frame);
        frame = 0;

        if (!document.hidden) {
            activity();
            resume();
        }
    });
        // Moodle loads dashboard cards asynchronously; discover additions without observing our canvas UI.

    const observer = new MutationObserver(records => {
        if (records.some(record => !root.contains(record.target))) {
            ledgesDirty = true;
        }
    });
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    window.addEventListener('pagehide', () => {
        surprises.pause();
        cancelAnimationFrame(frame);
        frame = 0;
        observer.disconnect();
    });
    window.addEventListener('pageshow', () => {
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        activity();
        resume();
    });
    window.addEventListener('resize', resize);
    resize();
    celebrations.setEnabled(enabled);
    celebrations.checkQuizzes();
    updateUi(false);
    resume();
};
