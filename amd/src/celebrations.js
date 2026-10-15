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
 * Opt-in jingles for confirmed completion and newly passed quizzes.
 * @module local_eledia_xmas/celebrations
 * @copyright 2026 eLeDia GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import CourseEvents from 'core_course/events';

/**
 * Attach celebration controls. Browser storage contains identifiers, never grades.
 * @param {HTMLElement} root Overlay.
 * @param {Object} config Server configuration.
 * @returns {Object} Effect controls.
 */
export const init = (root, config) => {
    const button = root.querySelector('[data-action="sound"]');
    const toast = root.querySelector('[data-region="celebration"]');
    // A season spanning New Year keeps its rewards under the year in which it started.
    const prefix = config.storagekey + ':rewards:' + (config.seasonyear || new Date().getFullYear());
    const soundkey = config.storagekey + ':sound';
    const seen = new Set();
    let sound = false;
    let enabled = true;
    let context = null;
    let timeout;
    let pending = null;
    let media = null;
    let greeting = false;
    const oscillators = new Set();
    let lastChime = -Infinity;
    const stopAudio = () => {
        media?.pause();
        media = null;
        greeting = false;
        oscillators.forEach(oscillator => oscillator.stop());
        oscillators.clear();
        pending = null;
    };
    const readItem = item => {
        try {
            return localStorage.getItem(item);
        } catch (_) {
            return null;
        }
    };
    const writeItem = (item, value) => {
        try {
            localStorage.setItem(item, value);
        } catch (_) {
            // Without durable storage, deduplicate for the current page only.
        }
    };
    // Rewards of earlier seasons are never read again; remove them so the browser storage does not grow each year.
    try {
        const stale = [];
        for (let index = 0; index < localStorage.length; index++) {
            const item = localStorage.key(index);
            if (item?.startsWith(config.storagekey + ':rewards:') && !item.startsWith(prefix + ':')) {
                stale.push(item);
            }
        }
        stale.forEach(item => localStorage.removeItem(item));
    } catch (_) {
        // Storage can be unavailable; nothing to clean up then.
    }
    const read = key => readItem(prefix + ':' + key);
    const write = (key, value) => writeItem(prefix + ':' + key, value);
    // The sound choice is a lasting preference, independent of the season.
    sound = readItem(soundkey) === 'true';
    const refresh = () => {
        const label = sound ? config.strings.soundoff : config.strings.soundon;
        button.setAttribute('aria-pressed', String(sound));
        button.setAttribute('aria-label', label);
        button.dataset.tooltip = label;
    };
    const synthesize = type => {
        const notes = {
            activity: [1047, 1319], quiz: [784, 988, 1175, 1568],
            course: [659, 784, 988, 1319, 1568], discovery: [1175, 1568, 1319],
        }[type];
        notes.forEach((frequency, index) => {
            const start = context.currentTime + index * 0.13;
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0, start);
            gain.gain.linearRampToValueAtTime(0.045, start + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.001, start + 0.65);
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillators.add(oscillator);
            oscillator.start(start);
            oscillator.stop(start + 0.7);
            oscillator.onended = () => {
                oscillators.delete(oscillator);
                oscillator.disconnect();
                gain.disconnect();
            };
        });
    };
    const chime = type => {
        if (!sound || !enabled || document.hidden || context?.state !== 'running') {
            return false;
        }
        if (performance.now() - lastChime < 5000 || greeting || (media && !media.paused)) {
            return true;
        }
        lastChime = performance.now();
        if (config.audio?.[type]) {
            const player = new Audio(config.audio[type]);
            media = player;
            player.volume = 0.45;
            player.play().catch(() => {
                if (media === player && sound && enabled && !document.hidden) {
                    synthesize(type);
                }
            });
        } else {
            synthesize(type);
        }
        return true;
    };
    const celebrate = (key, type) => {
        if (!enabled || seen.has(key) || read(key) === 'done') {
            return;
        }
        seen.add(key);
        write(key, 'done');
        toast.textContent = config.strings['celebrate' + type];
        toast.hidden = false;
        if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
            toast.animate([{opacity: 0, transform: 'translateY(8px)'}, {opacity: 1, transform: 'translateY(0)'}],
                {duration: 250});
        }
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            toast.hidden = true;
            pending = null;
        }, 6000);
        pending = chime(type) ? null : type;
    };
    button.addEventListener('click', async() => {
        sound = !sound;
        if (sound) {
            try {
                // The click only unlocks audio; switching jingles on stays silent, also for a waiting celebration.
                context = context || new AudioContext();
                await context.resume();
                lastChime = -Infinity;
                pending = null;
            } catch (_) {
                sound = false;
            }
        } else {
            stopAudio();
        }
        writeItem(soundkey, String(sound));
        refresh();
    });
    // Browsers require a fresh user gesture to unlock sound after navigation.
    const unlock = event => {
        if (event.target.closest?.('[data-action="sound"]')) {
            return;
        }
        if (sound && enabled) {
            try {
                context = context || new AudioContext();
                context.resume().then(() => {
                    if (pending !== null && chime(pending)) {
                        pending = null;
                    }
                    return;
                }).catch(() => {
                    // Browser may still require a direct click on the sound control.
                });
            } catch (_) {
                // Visual feedback remains available without Web Audio.
            }
        }
    };
    document.addEventListener('pointerdown', unlock, {passive: true});
    document.addEventListener('keydown', unlock);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopAudio();
        }
    });
    window.addEventListener('pagehide', stopAudio);
    document.addEventListener(CourseEvents.manualCompletionToggled, event => {
        if (event.detail.completed === true) {
            celebrate('activity:' + event.detail.cmid, 'activity');
        }
    });
    refresh();
    return {
        setEnabled: value => {
            enabled = value;
            button.hidden = !value;
            if (!value) {
                toast.hidden = true;
                stopAudio();
                context?.suspend().catch(() => {
                    // Playback has already stopped.
                });
            }
        },
        stopGreeting: () => {
            if (greeting) {
                stopAudio();
            }
        },
        playGreeting: (key, fallback) => {
            stopAudio();
            if (!enabled || document.hidden) {
                return;
            }
            greeting = true;
            if (config.audio?.[key]) {
                const player = new Audio(config.audio[key]);
                media = player;
                player.volume = 0.75;
                player.onended = () => {
                    if (media === player) {
                        greeting = false;
                    }
                };
                player.play().catch(() => {
                    if (media === player && enabled && !document.hidden) {
                        fallback();
                    }
                });
            } else {
                fallback();
            }
        },
        discover: key => celebrate('discovery:' + key, 'discovery'),
        checkQuizzes: () => {
            const states = {...config.completionstates};
            Object.entries(config.quizstates || {}).forEach(([id, passed]) => {
                states['quiz:' + id] = passed;
            });
            const rewards = [];
            Object.entries(states).forEach(([key, complete]) => {
                const previous = read(key);
                if (complete && previous === 'waiting') {
                    rewards.push(key);
                } else if (previous === null) {
                    // First visit establishes a baseline, never rewards old achievements.
                    write(key, complete ? 'done' : 'waiting');
                }
            });
            const priority = {course: 3, quiz: 2, activity: 1};
            rewards.sort((a, b) => priority[b.split(':')[0]] - priority[a.split(':')[0]]);
            rewards.forEach((key, index) => {
                if (index === 0) {
                    celebrate(key, key.split(':')[0]);
                } else {
                    seen.add(key);
                    write(key, 'done');
                }
            });
        },
    };
};
