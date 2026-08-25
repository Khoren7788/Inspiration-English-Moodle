<?php
defined('MOODLE_INTERNAL') || die();

echo html_writer::start_div('livecourse-layout');
echo html_writer::start_div('livecourse-panel');
echo html_writer::tag('h3', get_string('sessioncontrols', 'mod_livecourse'));
echo html_writer::div('', 'livecourse-status', ['id' => 'livecourse-status']);

foreach (['startsession', 'closequestion', 'endsession'] as $action) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/mod/livecourse/manage.php')]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    echo html_writer::tag('button', get_string($action, 'mod_livecourse'), ['class' => 'btn btn-secondary mb-2', 'type' => 'submit']);
    echo html_writer::end_tag('form');
}
echo html_writer::end_div();

echo html_writer::start_div('livecourse-panel');
echo html_writer::tag('h3', get_string('addquestion', 'mod_livecourse'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/mod/livecourse/manage.php')]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addquestion']);
echo html_writer::tag('label', get_string('questiontext', 'mod_livecourse'), ['for' => 'questiontext']);
echo html_writer::tag('textarea', '', ['id' => 'questiontext', 'name' => 'questiontext', 'required' => true, 'class' => 'form-control mb-2']);
foreach (['a', 'b', 'c', 'd'] as $option) {
    echo html_writer::tag('label', get_string('option', 'mod_livecourse', strtoupper($option)), ['for' => 'option' . $option]);
    echo html_writer::empty_tag('input', ['id' => 'option' . $option, 'name' => 'option' . $option, 'required' => true, 'class' => 'form-control mb-2']);
}
echo html_writer::tag('label', get_string('correctanswer', 'mod_livecourse'), ['for' => 'correctoption']);
echo html_writer::select(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'], 'correctoption', 'a', false, ['id' => 'correctoption', 'class' => 'form-select mb-2']);
echo html_writer::tag('button', get_string('savequestion', 'mod_livecourse'), ['class' => 'btn btn-primary', 'type' => 'submit']);
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('livecourse-panel livecourse-material-manager mb-4');
echo html_writer::tag('h3', get_string('addmaterial', 'mod_livecourse'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/mod/livecourse/manage.php')]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addmaterial']);
echo html_writer::tag('label', get_string('materialtitle', 'mod_livecourse'), ['for' => 'materialtitle']);
echo html_writer::empty_tag('input', ['id' => 'materialtitle', 'name' => 'materialtitle', 'required' => true, 'class' => 'form-control mb-2']);
echo html_writer::tag('label', get_string('materialtype', 'mod_livecourse'), ['for' => 'materialtype']);
echo html_writer::select([
    'video' => get_string('materialvideo', 'mod_livecourse'),
    'document' => get_string('materialdocument', 'mod_livecourse'),
    'link' => get_string('materiallink', 'mod_livecourse'),
], 'materialtype', 'link', false, ['id' => 'materialtype', 'class' => 'form-select mb-2']);
echo html_writer::tag('label', get_string('materialurl', 'mod_livecourse'), ['for' => 'materialurl']);
echo html_writer::empty_tag('input', ['id' => 'materialurl', 'name' => 'materialurl', 'type' => 'url', 'required' => true, 'class' => 'form-control mb-2']);
echo html_writer::tag('label', get_string('materialdescription', 'mod_livecourse'), ['for' => 'materialdescription']);
echo html_writer::tag('textarea', '', ['id' => 'materialdescription', 'name' => 'materialdescription', 'class' => 'form-control mb-2']);
echo html_writer::tag('button', get_string('savematerial', 'mod_livecourse'), ['class' => 'btn btn-primary', 'type' => 'submit']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

$questions = $DB->get_records('livecourse_question', ['livecourseid' => $livecourse->id], 'sortorder, id');
echo html_writer::tag('h3', get_string('questionbank', 'mod_livecourse'));
if (!$questions) {
    echo $OUTPUT->notification(get_string('noquestions', 'mod_livecourse'), 'info');
}
foreach ($questions as $question) {
    echo html_writer::start_div('livecourse-question-row');
    echo html_writer::div(format_text($question->questiontext, FORMAT_PLAIN), 'livecourse-question-title');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/mod/livecourse/manage.php')]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'publish']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $question->id]);
    echo html_writer::tag('button', get_string('publishquestion', 'mod_livecourse'), ['class' => 'btn btn-success', 'type' => 'submit']);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}
echo html_writer::div('', 'livecourse-stage mt-4', ['id' => 'livecourse-stage']);
