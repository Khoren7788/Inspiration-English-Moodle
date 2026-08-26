<?php
defined('MOODLE_INTERNAL') || die();

echo html_writer::start_div('lc-teaching-console');
echo html_writer::start_div('lc-teaching-console-heading');
echo html_writer::div(html_writer::tag('h2', get_string('teachingconsole', 'mod_livecourse')) .
    html_writer::tag('p', get_string('teachingconsoledescription', 'mod_livecourse')));
echo html_writer::span(get_string($initialactive ? 'sessionactive' : 'sessioninactive', 'mod_livecourse'),
    'lc-session-state' . ($initialactive ? ' is-active' : ''), ['id' => 'lc-session-state']);
echo html_writer::end_div();

echo html_writer::start_div('lc-teaching-actions');
$controls = [
    'startsession' => ['▶', 'btn-primary'],
    'endsession' => ['■', 'btn-outline-danger'],
];
foreach ($controls as $action => [$icon, $class]) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/livecourse/manage.php'),
        'class' => 'livecourse-realtime-form',
    ]);
    foreach (['id' => $cm->id, 'sesskey' => sesskey(), 'action' => $action] as $name => $value) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    echo html_writer::tag('button', html_writer::span($icon, 'lc-control-icon') . get_string($action, 'mod_livecourse'), [
        'class' => 'btn ' . $class,
        'type' => 'submit',
    ]);
    echo html_writer::end_tag('form');
}
echo html_writer::end_div();
echo html_writer::end_div();
