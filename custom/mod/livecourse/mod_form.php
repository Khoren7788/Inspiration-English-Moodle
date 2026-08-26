<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->libdir . '/filelib.php');

class mod_livecourse_mod_form extends moodleform_mod {
    private function editor_options(): array {
        global $COURSE;
        return [
            'maxfiles' => -1,
            'maxbytes' => $COURSE->maxbytes,
            'trusttext' => true,
            'context' => $this->context,
            'subdirs' => true,
        ];
    }

    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => 48, 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'contentsection', get_string('contentheader', 'page'));
        $mform->addElement('editor', 'page', get_string('content', 'page'), null,
            $this->editor_options());
        $mform->addRule('page', get_string('required'), 'required', null, 'client');

        $mform->addElement('header', 'appearancehdr', get_string('appearance'));
        $mform->addElement('advcheckbox', 'printintro', get_string('printintro', 'page'));
        $mform->setDefault('printintro', 1);
        $mform->addElement('advcheckbox', 'printlastmodified', get_string('printlastmodified', 'page'));
        $mform->setDefault('printlastmodified', 1);
        $mform->addElement('hidden', 'initialmaterialid', 0);
        $mform->setType('initialmaterialid', PARAM_INT);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$defaultvalues): void {
        global $DB;
        if (empty($this->current->instance)) {
            return;
        }
        $materials = $DB->get_records('livecourse_material', [
            'livecourseid' => $this->current->instance,
            'materialtype' => 'page',
        ], 'sortorder, id', '*', 0, 1);
        $material = $materials ? reset($materials) : false;
        if (!$material) {
            return;
        }
        $draftitemid = file_get_submitted_draft_itemid('page');
        $defaultvalues['page'] = [
            'format' => $material->contentformat,
            'text' => file_prepare_draft_area($draftitemid, $this->context->id, 'mod_livecourse',
                'content', $material->id, $this->editor_options(), $material->content),
            'itemid' => $draftitemid,
        ];
        $defaultvalues['initialmaterialid'] = $material->id;
        $defaultvalues['printintro'] = $material->displaydescription;
        $defaultvalues['printlastmodified'] = $material->displaylastmodified ?? 1;
    }
}
