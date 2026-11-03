<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
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

