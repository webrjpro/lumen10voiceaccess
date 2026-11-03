<?php
// Copyright 2026 Carlos Antonio de Oliveira Piquet. SPDX-License-Identifier: GPL-3.0-or-later.
namespace local_lumen10_voiceaccess\form;
defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');
final class access_form extends \moodleform {
    public function definition() {
        $m = $this->_form;
        $c = 'local_lumen10_voiceaccess';
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
        $m->addElement('text', 'voicephrase', 'Frase cadastrada para acesso por voz no login (ex.: a vida é bela)', [
            'placeholder' => 'Ex.: a vida é bela',
            'autocomplete' => 'off',
        ]);
        $m->setType('voicephrase', PARAM_TEXT);
        $m->addElement('static', 'privacyhelp', '', get_string('declarationhelp', $c));
        $this->add_action_buttons(false, get_string('approveperson', $c));
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['confirmed'])) {
            $errors['confirmed'] = get_string('invaliddeclaration', 'local_lumen10_voiceaccess');
        }
        if (!in_array($data['declaration'], \local_lumen10_voiceaccess\access::DECLARATIONS, true)) {
            $errors['declaration'] = get_string('invaliddeclaration', 'local_lumen10_voiceaccess');
        }
        if (empty($data['userid'])) {
            $errors['userid'] = get_string('required');
        }
        return $errors;
    }
}
