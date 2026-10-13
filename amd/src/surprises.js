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
 * Small, opt-in Christmas discoveries with optional device-local speech.
 * @module local_eledia_xmas/surprises
 * @copyright 2026 eLeDia GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Prepare hidden discoveries without starting audio or moving focus.
 * @param {HTMLElement} root Plugin overlay.
 * @param {Object} config Administrator setting.
 * @param {Object} audio Shared sound controls.
 * @returns {Object} Animation and pause callbacks.
 */
export const init = (root, config, audio) => {
    const allowed = config.eastereggs;
    const buttons = Array.from(root.querySelectorAll('[data-discovery]'));
    const panel = root.querySelector('.local-eledia-xmas-surprise');
    const speak = root.querySelector('[data-action="speak"]');
    const close = root.querySelector('[data-action="close-surprise"]');
    const character = root.querySelector('[data-greeting="character"]');
    const characters = {star: '🎅', bauble: '🦌', snowball: '⛄'};
    const discovered = new Set();
    const anchors = new Map();
    const hopped = new Set();
    const offsets = new Map();
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const anchorSelector = '[data-xmas-image], #courseheaderimage, .dashboard-card .card-img-top, '
        + '.course-card .card-img-top, .coursebox .courseimage img, [data-xmas-ledge]';
    let active = false;
    let nextSearch = 0;
    let opener = null;
    let sentence = '';
    let utterance = null;
    const language = document.documentElement.lang || 'en';
    const localVoice = () => window.speechSynthesis?.getVoices().find(voice =>
        voice.localService && voice.lang.toLowerCase().startsWith(language.slice(0, 2).toLowerCase()));
    const stopSpeech = () => {
        audio.stopGreeting();
        if (utterance) {
            utterance.onend = utterance.onerror = null;
            window.speechSynthesis.cancel();
            utterance = null;
        }
    };
    const dismiss = () => {
        stopSpeech();
        panel.hidden = true;
        if (opener) {
            opener.setAttribute('aria-expanded', 'false');
        }
    };
    // Keep each discovery on the same content surface, including inside Moodle's scrolling page container.
    const position = () => {
        if (!active) {
            buttons.forEach(button => {
                button.hidden = true;
            });
            return;
        }
        if (buttons.some(button => !anchors.get(button)?.isConnected) && performance.now() >= nextSearch) {
            nextSearch = performance.now() + 500;
            const candidates = Array.from(document.querySelectorAll(anchorSelector)).filter(element => {
                const rect = element.getBoundingClientRect();
                return rect.width >= 140 && rect.height >= 60 && getComputedStyle(element).visibility === 'visible';
            });
            buttons.forEach((button, index) => {
                if (!anchors.get(button)?.isConnected && candidates.length) {
                    anchors.set(button, candidates[index % candidates.length]);
                }
            });
        }
        buttons.forEach((button, index) => {
            const anchor = anchors.get(button);
            if (!discovered.has(button) || !anchor?.isConnected) {
                button.hidden = true;
                return;
            }
            const rect = anchor.getBoundingClientRect();
            const x = rect.left + rect.width * [0.2, 0.5, 0.8][index] - 22 + (offsets.get(button) || 0);
            const y = rect.bottom - 48;
            // Hide with the surface instead of pinning the discovery to the viewport edge.
            // An open greeting stays readable: the panel is fixed and does not depend on the button position.
            if (rect.height < 60 || rect.width < 140 || x < 0 || x + 44 > innerWidth || y < 0 || y + 44 > innerHeight
                    || getComputedStyle(anchor).visibility !== 'visible') {
                button.hidden = true;
                return;
            }
            button.style.left = `${x}px`;
            button.style.top = `${y}px`;
            button.hidden = false;
        });
    };
    buttons.forEach(button => button.addEventListener('pointerenter', event => {
        if (event.pointerType !== 'mouse' || reducedMotion.matches || hopped.has(button) || button === document.activeElement) {
            return;
        }
        hopped.add(button);
        offsets.set(button, button.dataset.discovery === 'snowball' ? -32 : 32);
        button.animate([{transform: 'translateY(0)'}, {transform: 'translateY(-14px)'},
            {transform: 'translateY(0)'}], {duration: 280, easing: 'ease-out'});
        position();
    }));
    buttons.forEach(button => button.addEventListener('click', () => {
        dismiss();
        opener = button;
        const key = button.dataset.discovery;
        character.textContent = characters[key];
        root.querySelectorAll('p[data-greeting]').forEach(message => {
            message.hidden = message.dataset.greeting !== key;
            if (!message.hidden) {
                sentence = message.textContent;
            }
        });
        panel.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        speak.hidden = !config.audio?.[key] && !localVoice();
        panel.focus({preventScroll: true});
        audio.discover(key);
    }));
    // Return focus to the discovery, or to the snow control when the discovery has scrolled out of view.
    const closeAndRestoreFocus = () => {
        dismiss();
        const target = opener && !opener.hidden ? opener : root.querySelector('[data-action="toggle"]');
        target?.focus({preventScroll: true});
    };
    close.addEventListener('click', closeAndRestoreFocus);
    panel.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeAndRestoreFocus();
        }
    });
    speak.addEventListener('click', () => {
        stopSpeech();
        audio.playGreeting(opener?.dataset.discovery, () => {
            const voice = localVoice();
            if (!voice) {
                speak.hidden = true;
                audio.stopGreeting();
                return;
            }
            utterance = new SpeechSynthesisUtterance(sentence);
            utterance.voice = voice;
            utterance.lang = voice.lang;
            utterance.rate = 0.9;
            utterance.onend = utterance.onerror = () => {
                utterance = null;
                audio.stopGreeting();
            };
            window.speechSynthesis.speak(utterance);
        });
    });
    window.speechSynthesis?.addEventListener('voiceschanged', () => {
        speak.hidden = !config.audio?.[opener?.dataset.discovery] && !localVoice();
    });
    return {
        position,
        update: (seconds, enabled, fast) => {
            active = enabled && allowed;
            if (!enabled) {
                dismiss();
            }
            buttons.forEach((button, index) => {
                if (allowed && seconds >= (index + 1) * (fast ? 3 : 12)) {
                    discovered.add(button);
                }

            });
            position();
        },
        pause: stopSpeech,
    };
};
