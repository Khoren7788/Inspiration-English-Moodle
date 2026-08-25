<?php
require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('livecourse', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$livecourse = $DB->get_record('livecourse', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/livecourse:view', $context);

$PAGE->set_url('/mod/livecourse/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($livecourse->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/mod/livecourse/styles.css');
$PAGE->requires->js_call_amd('mod_livecourse/live', 'init', [[
    'cmid' => $cm->id,
    'teacher' => has_capability('mod/livecourse:manage', $context),
    'sesskey' => sesskey(),
    'strings' => [
        'waiting' => get_string('waitingforquestion', 'mod_livecourse'),
        'submitted' => get_string('answersubmitted', 'mod_livecourse'),
        'closed' => get_string('sessionclosed', 'mod_livecourse'),
    ],
]]);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($livecourse->name));
if (!empty($livecourse->intro)) {
    echo $OUTPUT->box(format_module_intro('livecourse', $livecourse, $cm->id), 'generalbox mod_introbox');
}

require(__DIR__ . '/materials_view.php');

if (has_capability('mod/livecourse:manage', $context)) {
    require(__DIR__ . '/teacher_view.php');
} else {
    require(__DIR__ . '/student_view.php');
}
echo $OUTPUT->footer();
