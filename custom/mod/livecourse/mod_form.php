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
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}

