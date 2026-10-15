// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Remove this user's eLeDia | Xmas entries from the browser storage.
 *
 * @module     local_eledia_xmas/forget
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Wire the removal button.
 * @param {String} storagekey Key prefix of the current user on this site.
 */
export const init = storagekey => {
    const button = document.querySelector('[data-action="local-eledia-xmas-forget"]');
    const status = document.querySelector('[data-region="local-eledia-xmas-forget-status"]');

    if (!button || !status) {
        return;
    }

    button.addEventListener('click', () => {
        try {
            const items = [];
            for (let index = 0; index < localStorage.length; index++) {
                const item = localStorage.key(index);
                if (item === storagekey || item?.startsWith(storagekey + ':')) {
                    items.push(item);
                }
            }
            items.forEach(item => localStorage.removeItem(item));
            status.textContent = button.dataset.done;
        } catch (_) {
            status.textContent = button.dataset.failed;
        }
    });
};
