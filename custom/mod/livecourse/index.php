<?php
require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/livecourse/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_livecourse'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_livecourse'));
$instances = get_all_instances_in_course('livecourse', $course);
if (!$instances) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_livecourse')),
        new moodle_url('/course/view.php', ['id' => $course->id]));
}
$table = new html_table();
$table->head = [get_string('name')];
foreach ($instances as $instance) {
    $table->data[] = [html_writer::link(
        new moodle_url('/mod/livecourse/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name)
    )];
}
echo html_writer::table($table);
echo $OUTPUT->footer();

