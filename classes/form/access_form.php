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
 * classes/form/access_form.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess\form;
defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');
final class access_form extends \moodleform {
    public function definition() {
        $m = $this->_form;
        $c = 'mod_lumen10voiceaccess';
        $m->addElement('header', 'approveheading', get_string('approveperson', $c));
        $m->addElement('autocomplete', 'userid', get_string('person', $c), $this->_customdata['users'], [
            'multiple' => false, 'tags' => false, 'showsuggestions' => true,
            'placeholder' => get_string('chooseperson', $c)]);
        $m->setType('userid', PARAM_INT);
        $m->addRule('userid', null, 'required', null, 'client');
        $m->addElement('select', 'declaration', get_string('declaration', $c), [
            '' => get_string('choose'), 'blind' => get_string('declarationblind', $c),
            'severemobility' => get_string('declarationmobility', $c), 'both' => get_string('declarationboth', $c)]);
        $m->setType('declaration', PARAM_ALPHA);
        $m->addElement('advcheckbox', 'confirmed', get_string('declarationconfirmed', $c));
        $m->addElement('text', 'voicephrase', get_string('voicephrase', $c), [
            'placeholder' => get_string('voicephrase_placeholder', $c),
            'autocomplete' => 'off',
        ]);
        $m->setType('voicephrase', PARAM_TEXT);
        $m->addElement('static', 'voicephrasehelp', '', get_string('voicephrase_help', $c));
        $m->addElement('static', 'privacyhelp', '', get_string('declarationhelp', $c));
        $this->add_action_buttons(false, get_string('approveperson', $c));
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['confirmed'])) {
            $errors['confirmed'] = get_string('invaliddeclaration', 'mod_lumen10voiceaccess');
        }
        if (!in_array($data['declaration'], \mod_lumen10voiceaccess\access::DECLARATIONS, true)) {
            $errors['declaration'] = get_string('invaliddeclaration', 'mod_lumen10voiceaccess');
        }
        if (empty($data['userid'])) {
            $errors['userid'] = get_string('required');
        }
        return $errors;
    }
}
