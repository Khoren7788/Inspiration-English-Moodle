<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class livecourse_page_form extends moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $cmid = $this->_customdata['cmid'];
        $materialid = $this->_customdata['materialid'];
        $editoroptions = $this->_customdata['editoroptions'];

        $mform->addElement('hidden', 'id', $cmid);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'materialid', $materialid);
        $mform->setType('materialid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('materialtitle', 'mod_livecourse'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('editor', 'description_editor', get_string('materialdescription', 'mod_livecourse'),
            ['rows' => 5], ['maxfiles' => 0, 'context' => $this->_customdata['context']]);
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addElement('advcheckbox', 'displaydescription', get_string('displaypagedescription', 'mod_livecourse'));
        $mform->setDefault('displaydescription', 1);

        $mform->addElement('editor', 'content_editor', get_string('materialcontent', 'mod_livecourse'),
            ['rows' => 20], $editoroptions);
        $mform->setType('content_editor', PARAM_RAW);
        $mform->addRule('content_editor', null, 'required', null, 'client');

        $mform->addElement('header', 'appearance', get_string('appearance'));
        $mform->addElement('advcheckbox', 'displaytitle', get_string('displaypagetitle', 'mod_livecourse'));
        $mform->setDefault('displaytitle', 1);

        $this->add_action_buttons(true, get_string('savematerialchanges', 'mod_livecourse'));
    }
}
