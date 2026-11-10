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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * classes/form/preferences_form.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess\form;
defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

final class preferences_form extends \moodleform {
    public function definition() {
        $m = $this->_form;
        $c = 'mod_lumen10voiceaccess';
        $m->addElement('header', 'access', get_string('settings', $c));
        $m->addElement('select', 'profile', get_string('profile', $c), [
            'standard' => get_string('standard', $c), 'screenreader' => get_string('screenreader', $c),
            'handsfree' => get_string('handsfree', $c),
        ]);
        foreach (['spoken' => 'speech', 'wakeword' => 'wakeword', 'contrast' => 'contrast', 'large' => 'large'] as $name => $key) {
            $m->addElement('advcheckbox', $name, get_string($key, $c));
        }
        $m->addElement('select', 'recognitionmode', get_string('recognitionmode', $c), [
            'browser' => get_string('recognitionbrowser', $c), 'device' => get_string('recognitiondevice', $c),
        ]);
        $m->addElement('static', 'recognitionhelp', '', get_string('recognitionhelp', $c));
        $m->addElement('advcheckbox', 'resume', get_string('resume', $c));
        $m->addElement('static', 'resumehelp', '', get_string('resumehelp', $c));
        $m->addElement('button', 'installvoice', get_string('installvoice', $c));
        $m->addElement('static', 'installvoicestatus', '', '');
        $m->addElement('header', 'voice', get_string('voicesettings', $c));
        $m->addElement('select', 'rate', get_string('rate', $c), [
            '0.5' => '0.5×', '0.75' => '0.75×', '1' => '1×', '1.25' => '1.25×', '1.5' => '1.5×', '2' => '2×',
        ]);
        $row = \mod_lumen10voiceaccess\preferences::get((int)$GLOBALS['USER']->id);
        $prefs = \mod_lumen10voiceaccess\preferences::public_settings($row);
        $voices = ['' => get_string('voiceauto', $c)];
        if ($prefs['voiceuri'] !== '') {
            $voices[$prefs['voiceuri']] = $prefs['voiceuri'];
        }
        $submittedvoice = optional_param('voiceuri', '', PARAM_TEXT);
        if ($submittedvoice !== '' && \core_text::strlen($submittedvoice) <= 255) {
            $voices[$submittedvoice] = $submittedvoice;
        }
        // Available voices are populated on this user's browser, never from a server-wide list.
        $m->addElement('select', 'voiceuri', get_string('voice', $c), $voices);
        $m->setType('voiceuri', PARAM_TEXT);
        $m->addElement('static', 'voicehelp', '', get_string('voicehelp', $c));
        $m->addElement('button', 'voicetest', get_string('voicetest', $c));
        $m->addElement('static', 'voiceteststatus', '',
            '<span id="l10va-voice-test-status" role="status" aria-live="polite"></span>');
        $m->addElement('select', 'pitch', get_string('pitch', $c), [
            '0.5' => '0.5', '0.75' => '0.75', '1' => '1', '1.25' => '1.25', '1.5' => '1.5', '2' => '2',
        ]);
        $m->addElement('select', 'volume', get_string('volume', $c), [
            '0.25' => '25%', '0.5' => '50%', '0.75' => '75%', '1' => '100%',
        ]);
        $m->addElement('select', 'scaninterval', get_string('scaninterval', $c), array_combine(range(2, 10), range(2, 10)));
        $m->addElement('header', 'ai', get_string('aiheading', $c));
        $m->addElement('static', 'aidisclosure', '', get_string('aidisclosure', $c));
        $m->addElement('static', 'aistate', '', get_string('aistate', $c));
        $m->addElement('select', 'provider', get_string('provider', $c), [
            'none' => get_string('none', $c), 'openai' => 'OpenAI — GPT-6 Luna', 'anthropic' => 'Anthropic — Claude',
        ]);
        $m->addElement('text', 'model', get_string('model', $c), ['maxlength' => 100, 'placeholder' => 'gpt-6-luna', 'spellcheck' => 'false', 'autocapitalize' => 'none']);
        $m->setType('model', PARAM_RAW_TRIMMED);
        $m->addElement('static', 'keystate', '', get_string(empty($row->apikey) ? 'keyabsent' : 'keypresent', $c));
        $m->addElement('password', 'apikey', get_string('apikey', $c), ['autocomplete' => 'new-password', 'maxlength' => 512, 'placeholder' => get_string('keyplaceholder', $c), 'spellcheck' => 'false', 'autocapitalize' => 'none', 'autocorrect' => 'off', 'size' => 50]);
        $m->setType('apikey', PARAM_RAW_TRIMMED);
        $m->addElement('static', 'keyhelp', '', get_string('apikey_help', $c));
        $m->addElement('advcheckbox', 'removekey', get_string('removekey', $c));
        $m->addElement('advcheckbox', 'consent', get_string('consent', $c));
        $m->setExpanded('access', true);
        $m->setExpanded('voice', true);
        $m->setExpanded('ai', true);
        $this->add_action_buttons(false, get_string('save', $c));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (\core_text::strlen($data['voiceuri'] ?? '') > 255) {
            $errors['voiceuri'] = get_string('invalidcommand', 'mod_lumen10voiceaccess');
        }
        if ($data['provider'] !== 'none' && !preg_match('/^[a-zA-Z0-9._:\/-]{1,100}$/D', $data['model'])) {
            $errors['model'] = get_string('invalidmodel', 'mod_lumen10voiceaccess');
        }
        if (preg_match('/[\r\n]/', $data['apikey']) || strlen($data['apikey']) > 512) {
            $errors['apikey'] = get_string('invalidcommand', 'mod_lumen10voiceaccess');
        }
        return $errors;
    }
}

