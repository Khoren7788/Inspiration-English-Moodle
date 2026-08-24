<?php
defined('MOODLE_INTERNAL') || die();

echo html_writer::div(get_string('waitingforquestion', 'mod_livecourse'), 'livecourse-status', ['id' => 'livecourse-status']);
echo html_writer::div('', 'livecourse-stage', ['id' => 'livecourse-stage']);

