<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_livecourse_mod_form extends moodleform_mod {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('livecoursename', 'mod_livecourse'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();
        $mform->addElement('header', 'videoheading', get_string('videocallsettings', 'mod_livecourse'));
        $mform->addElement('advcheckbox', 'meetingenabled', get_string('enablevideocall', 'mod_livecourse'));
        $mform->setDefault('meetingenabled', 0);
        $mform->addElement('url', 'meetingurl', get_string('meetingurl', 'mod_livecourse'), ['size' => 64]);
        $mform->setType('meetingurl', PARAM_URL);
        $mform->hideIf('meetingurl', 'meetingenabled', 'notchecked');
        $mform->addHelpButton('meetingurl', 'meetingurl', 'mod_livecourse');
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
