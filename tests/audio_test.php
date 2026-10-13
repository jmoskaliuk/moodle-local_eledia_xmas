<?php
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_eledia_xmas;

/**
 * Verify that recorded greetings match their language strings and are only offered when they fit.
 * @package local_eledia_xmas
 * @copyright 2026 eLeDia GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
final class audio_test extends \advanced_testcase {
    /**
     * Every recording exists and its transcript equals the shipped string of its language pack.
     */
    public function test_transcripts_match_language_files(): void {
        $greetings = ['star' => 'santagreeting', 'bauble' => 'reindeergreeting', 'snowball' => 'snowmangreeting'];
        $root = __DIR__ . '/..';
        $transcripts = json_decode(file_get_contents($root . '/audio/transcripts.json'), true);
        $this->assertNotEmpty($transcripts);
        foreach ($transcripts as $language => $recordings) {
            $string = [];
            include($root . '/lang/' . $language . '/local_eledia_xmas.php');
            foreach ($recordings as $key => $text) {
                $this->assertArrayHasKey($key, $greetings, "$language/$key");
                $this->assertFileExists($root . '/audio/' . $language . '/' . $key . '.mp3');
                $this->assertSame($string[$greetings[$key]], $text, "$language/$key");
            }
        }
    }

    /**
     * Jingles are always offered; greetings only where a matching recording exists.
     */
    public function test_audio_urls_offer_only_matching_greetings(): void {
        $transcripts = json_decode(file_get_contents(__DIR__ . '/../audio/transcripts.json'), true);
        $audio = hook_callbacks::audio_urls();
        foreach (['activity', 'quiz', 'course', 'discovery'] as $jingle) {
            $this->assertArrayHasKey($jingle, $audio);
        }
        foreach (['star', 'bauble', 'snowball'] as $key) {
            $this->assertSame(isset($transcripts['en'][$key]), isset($audio[$key]), $key);
        }
    }
}
