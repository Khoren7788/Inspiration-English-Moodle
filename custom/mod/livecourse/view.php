<?php
require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('livecourse', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$livecourse = $DB->get_record('livecourse', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/livecourse:view', $context);
$isteacher = has_capability('mod/livecourse:manage', $context);

$PAGE->set_url('/mod/livecourse/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($livecourse->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/mod/livecourse/styles.css');
$PAGE->requires->js_call_amd('mod_livecourse/live', 'init', [[
    'cmid' => $cm->id,
    'teacher' => $isteacher,
    'sesskey' => sesskey(),
    'wstoken' => livecourse_create_websocket_token($USER->id, $cm->id),
    'strings' => [
        'waiting' => get_string('waitingforquestion', 'mod_livecourse'),
        'submitted' => get_string('answersubmitted', 'mod_livecourse'),
        'closed' => get_string('sessionclosed', 'mod_livecourse'),
        'submit' => get_string('submitanswer', 'mod_livecourse'),
        'choose' => get_string('choosematch', 'mod_livecourse'),
        'responses' => get_string('responses', 'mod_livecourse'),
        'correct' => get_string('correctresponses', 'mod_livecourse'),
        'waitingmaterial' => get_string('waitingformaterial', 'mod_livecourse'),
        'openmaterial' => get_string('openmaterial', 'mod_livecourse'),
        'startlesson' => get_string('startlesson', 'mod_livecourse'),
        'readytitle' => get_string('readytitle', 'mod_livecourse'),
        'readydescription' => get_string('readydescription', 'mod_livecourse'),
        'studentwaitingtitle' => get_string('studentwaitingtitle', 'mod_livecourse'),
        'studentwaitingdescription' => get_string('studentwaitingdescription', 'mod_livecourse'),
        'actionfailed' => get_string('actionfailed', 'mod_livecourse'),
    ],
]]);

echo $OUTPUT->header();
if (!empty($livecourse->intro)) {
    echo $OUTPUT->box(format_module_intro('livecourse', $livecourse, $cm->id), 'generalbox mod_introbox');
}

$materialconditions = ['livecourseid' => $livecourse->id];
if (!$isteacher) {
    $materialconditions['visible'] = 1;
}
$classroommaterials = $DB->get_records('livecourse_material', $materialconditions, 'sortorder, id');
$initialactive = $DB->record_exists('livecourse_session', ['livecourseid' => $livecourse->id, 'status' => 1]);

$appclasses = 'lc-app' . ($isteacher && !$initialactive ? ' lc-awaiting-start' : '');
echo html_writer::start_div($appclasses, ['id' => 'inspiration-liveclassroom']);
echo html_writer::start_tag('aside', ['class' => 'lc-sidebar']);
echo html_writer::start_div('lc-side-top');
echo html_writer::tag('button', '☰', ['type' => 'button', 'class' => 'lc-icon-btn', 'id' => 'lc-menu-btn',
    'aria-label' => get_string('togglelessonplan', 'mod_livecourse')]);
echo html_writer::div('Inspiration', 'lc-logo');
echo html_writer::end_div();
echo html_writer::div(
    html_writer::tag('strong', get_string($isteacher ? 'teacherarea' : 'classroom', 'mod_livecourse')) .
    html_writer::empty_tag('br') . html_writer::span(format_string($livecourse->name)),
    'lc-teacher-box'
);
echo html_writer::start_div('lc-side-tabs');
echo html_writer::div(get_string('lessonplan', 'mod_livecourse'), 'active');
echo html_writer::end_div();
echo html_writer::start_div('lc-lesson-list');
if (!$classroommaterials) {
    echo html_writer::div(get_string('nomaterials', 'mod_livecourse'), 'lc-empty-list');
}
$materialnumber = 0;
foreach ($classroommaterials as $material) {
    $materialnumber++;
    $buttoncontent = html_writer::span((string) $materialnumber, 'lc-num') .
        html_writer::span(format_string($material->title), 'lc-item-title') . html_writer::span('›');
    if ($isteacher) {
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/mod/livecourse/manage.php'),
            'class' => 'livecourse-realtime-form lc-lesson-form',
        ]);
        foreach (['id' => $cm->id, 'sesskey' => sesskey(), 'materialid' => $material->id] as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'showmaterial']);
        echo html_writer::tag('button', $buttoncontent, [
            'type' => 'submit',
            'class' => 'lc-lesson-item' . ($material->visible ? '' : ' lc-hidden-material'),
            'data-livecourse-material' => $material->id,
        ]);
        echo html_writer::end_tag('form');
    } else {
        echo html_writer::tag('button', $buttoncontent, [
            'type' => 'button',
            'class' => 'lc-lesson-item',
            'data-livecourse-material' => $material->id,
            'disabled' => true,
        ]);
    }
}
echo html_writer::end_div();
echo html_writer::end_tag('aside');

echo html_writer::start_tag('main', ['class' => 'lc-main']);
echo html_writer::start_div('lc-topbar');
echo html_writer::start_tag('nav', ['class' => 'lc-tabs']);
echo html_writer::span(get_string('lesson', 'mod_livecourse'), 'lc-tab active');
echo html_writer::end_tag('nav');
echo html_writer::tag('button', '⇤', ['type' => 'button', 'class' => 'lc-icon-btn', 'id' => 'lc-collapse-btn',
    'aria-label' => get_string('togglelessonplan', 'mod_livecourse')]);
echo html_writer::end_div();
if ($isteacher) {
    echo html_writer::start_div('lc-teacher-linkbar');
    echo html_writer::tag('strong', get_string('studentclassroomlink', 'mod_livecourse'));
    echo html_writer::span($PAGE->url->out(false), 'lc-link', ['id' => 'lc-classroom-link']);
    echo html_writer::tag('button', get_string('copylink', 'mod_livecourse'), [
        'type' => 'button', 'class' => 'btn btn-sm btn-primary', 'id' => 'lc-copy-btn',
    ]);
    echo html_writer::end_div();
}
echo html_writer::start_tag('section', ['class' => 'lc-content']);
echo html_writer::div('', 'livecourse-status lc-live-status', ['id' => 'livecourse-status']);
echo html_writer::start_div('livecourse-player');
echo html_writer::div('', 'livecourse-content-stage', ['id' => 'livecourse-content-stage']);
echo html_writer::div('', 'livecourse-stage', ['id' => 'livecourse-stage']);
echo html_writer::end_div();
echo html_writer::end_tag('section');
echo html_writer::end_tag('main');
echo html_writer::end_div();

if ($isteacher) {
    echo html_writer::start_tag('details', ['class' => 'lc-authoring', 'open' => 'open']);
    echo html_writer::start_tag('summary');
    echo html_writer::span(get_string('authoringtools', 'mod_livecourse'), 'lc-authoring-title');
    echo html_writer::span(get_string('authoringsubtitle', 'mod_livecourse'), 'lc-authoring-subtitle');
    echo html_writer::end_tag('summary');
    echo html_writer::start_div('lc-authoring-body');
    require(__DIR__ . '/teacher_view.php');
    require(__DIR__ . '/materials_view.php');
    echo html_writer::end_div();
    echo html_writer::end_tag('details');
} else {
    require(__DIR__ . '/student_view.php');
}
echo $OUTPUT->footer();
