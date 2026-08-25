<?php
defined('MOODLE_INTERNAL') || die();

echo html_writer::start_div('lc-control-panel');
echo html_writer::start_div('lc-section-heading');
echo html_writer::div(html_writer::tag('h3', get_string('sessioncontrols', 'mod_livecourse')) .
    html_writer::tag('p', get_string('sessioncontrolssubtitle', 'mod_livecourse')));
echo html_writer::span(get_string('live', 'mod_livecourse'), 'lc-live-pill');
echo html_writer::end_div();
echo html_writer::start_div('lc-control-actions');

$controls = [
    'startsession' => ['▶', 'btn-primary'],
    'previousmaterial' => ['←', 'btn-outline-secondary'],
    'nextmaterial' => ['→', 'btn-outline-secondary'],
    'closematerial' => ['×', 'btn-outline-secondary'],
    'closequestion' => ['✓', 'btn-outline-secondary'],
    'endsession' => ['■', 'btn-outline-danger'],
];
foreach ($controls as $action => [$icon, $class]) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/livecourse/manage.php'),
        'class' => 'livecourse-realtime-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    echo html_writer::tag('button', html_writer::span($icon, 'lc-control-icon') . get_string($action, 'mod_livecourse'),
        ['class' => 'btn ' . $class, 'type' => 'submit']);
    echo html_writer::end_tag('form');
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('lc-builder-grid');
echo html_writer::start_div('livecourse-panel lc-composer-card');
echo html_writer::start_div('lc-section-heading');
echo html_writer::div(html_writer::tag('h3', get_string('addquestion', 'mod_livecourse')) .
    html_writer::tag('p', get_string('addquestionsubtitle', 'mod_livecourse')));
echo html_writer::span('01', 'lc-step-badge');
echo html_writer::end_div();
echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/mod/livecourse/manage.php')]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addquestion']);
echo html_writer::tag('label', get_string('questiontype', 'mod_livecourse'), ['for' => 'questiontype']);
echo html_writer::select([
    'multichoice' => get_string('typemultichoice', 'mod_livecourse'),
    'truefalse' => get_string('typetruefalse', 'mod_livecourse'),
    'shortanswer' => get_string('typeshortanswer', 'mod_livecourse'),
    'gapfill' => get_string('typegapfill', 'mod_livecourse'),
    'matching' => get_string('typematching', 'mod_livecourse'),
], 'questiontype', 'multichoice', false, ['id' => 'questiontype', 'class' => 'form-select mb-2']);
echo html_writer::tag('label', get_string('questiontext', 'mod_livecourse'), ['for' => 'questiontext']);
echo html_writer::tag('textarea', '', ['id' => 'questiontext', 'name' => 'questiontext', 'required' => true, 'class' => 'form-control mb-2']);
echo html_writer::start_div('', ['data-question-fields' => 'multichoice']);
foreach (['a', 'b', 'c', 'd'] as $option) {
    echo html_writer::tag('label', get_string('option', 'mod_livecourse', strtoupper($option)), ['for' => 'option' . $option]);
    echo html_writer::empty_tag('input', ['id' => 'option' . $option, 'name' => 'option' . $option, 'class' => 'form-control mb-2']);
}
echo html_writer::tag('label', get_string('correctanswer', 'mod_livecourse'), ['for' => 'correctoption']);
echo html_writer::select(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'], 'correctoption', 'a', false, ['id' => 'correctoption', 'class' => 'form-select mb-2']);
echo html_writer::end_div();
echo html_writer::start_div('', ['data-question-fields' => 'truefalse']);
echo html_writer::tag('label', get_string('truefalseanswer', 'mod_livecourse'), ['for' => 'truefalseanswer']);
echo html_writer::select(['true' => get_string('true', 'core'), 'false' => get_string('false', 'core')], 'truefalseanswer', 'true', false, ['id' => 'truefalseanswer', 'class' => 'form-select mb-2']);
echo html_writer::end_div();
echo html_writer::start_div('', ['data-question-fields' => 'shortanswer gapfill']);
echo html_writer::tag('label', get_string('textanswer', 'mod_livecourse'), ['for' => 'textanswer']);
echo html_writer::empty_tag('input', ['id' => 'textanswer', 'name' => 'textanswer', 'class' => 'form-control mb-2']);
echo html_writer::end_div();
echo html_writer::start_div('', ['data-question-fields' => 'matching']);
echo html_writer::tag('label', get_string('matchingpairs', 'mod_livecourse'), ['for' => 'matchingpairs']);
echo html_writer::tag('textarea', '', ['id' => 'matchingpairs', 'name' => 'matchingpairs', 'class' => 'form-control mb-2', 'placeholder' => "Armenia = Yerevan\nFrance = Paris"]);
echo html_writer::end_div();
echo html_writer::tag('button', get_string('savequestion', 'mod_livecourse'), ['class' => 'btn btn-primary', 'type' => 'submit']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo html_writer::start_div('livecourse-panel livecourse-material-manager lc-composer-card');
echo html_writer::start_div('lc-section-heading');
echo html_writer::div(html_writer::tag('h3', get_string('addmaterial', 'mod_livecourse')) .
    html_writer::tag('p', get_string('addmaterialsubtitle', 'mod_livecourse')));
echo html_writer::span('02', 'lc-step-badge');
echo html_writer::end_div();
echo html_writer::link(new moodle_url('/mod/livecourse/page.php', ['id' => $cm->id]),
    html_writer::span('+', 'lc-add-icon') . get_string('addcontentpage', 'mod_livecourse'),
    ['class' => 'lc-rich-page-link']);
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
echo html_writer::start_div('', ['data-material-fields' => 'url']);
echo html_writer::tag('label', get_string('materialurl', 'mod_livecourse'), ['for' => 'materialurl']);
echo html_writer::empty_tag('input', ['id' => 'materialurl', 'name' => 'materialurl', 'type' => 'url', 'class' => 'form-control mb-2']);
echo html_writer::end_div();
echo html_writer::tag('label', get_string('materialdescription', 'mod_livecourse'), ['for' => 'materialdescription']);
echo html_writer::tag('textarea', '', ['id' => 'materialdescription', 'name' => 'materialdescription', 'class' => 'form-control mb-2']);
echo html_writer::tag('button', get_string('savematerial', 'mod_livecourse'), ['class' => 'btn btn-primary', 'type' => 'submit']);
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

$questions = $DB->get_records('livecourse_question', ['livecourseid' => $livecourse->id], 'sortorder, id');
echo html_writer::start_div('lc-library-panel lc-question-library');
echo html_writer::start_div('lc-section-heading');
echo html_writer::div(html_writer::tag('h3', get_string('questionbank', 'mod_livecourse')) .
    html_writer::tag('p', get_string('questionbanksubtitle', 'mod_livecourse')));
echo html_writer::span((string) count($questions), 'lc-count-badge');
echo html_writer::end_div();
if (!$questions) {
    echo $OUTPUT->notification(get_string('noquestions', 'mod_livecourse'), 'info');
}
foreach ($questions as $question) {
    echo html_writer::start_div('livecourse-question-row');
    echo html_writer::div(format_text($question->questiontext, FORMAT_PLAIN) .
        html_writer::span(get_string('type' . $question->questiontype, 'mod_livecourse'), 'badge bg-secondary ms-2'),
        'livecourse-question-title');
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/livecourse/manage.php'),
        'class' => 'livecourse-realtime-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'publish']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $question->id]);
    echo html_writer::tag('button', get_string('publishquestion', 'mod_livecourse'), ['class' => 'btn btn-success', 'type' => 'submit']);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}
echo html_writer::end_div();
