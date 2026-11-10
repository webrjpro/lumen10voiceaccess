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
 * mod_form.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');
class mod_lumen10voiceaccess_mod_form extends moodleform_mod {
    public function definition() {
        $m = $this->_form;
        $m->addElement('header', 'general', get_string('general', 'form'));
        $m->addElement('text', 'name', get_string('name'), ['size' => 60, 'maxlength' => 255]);
        $m->setType('name', PARAM_TEXT);
        $m->addRule('name', null, 'required', null, 'client');
        $m->setDefault('name', get_string('pluginname', 'mod_lumen10voiceaccess'));
        $this->standard_intro_elements();
        $m->addElement('static', 'scope', '', get_string('scope', 'mod_lumen10voiceaccess'));
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}

