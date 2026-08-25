<?php
defined('MOODLE_INTERNAL') || die();

$isteacher = has_capability('mod/livecourse:manage', $context);
$materialconditions = ['livecourseid' => $livecourse->id];
if (!$isteacher) {
    $materialconditions['visible'] = 1;
}
$materials = $DB->get_records('livecourse_material', $materialconditions, 'sortorder, id');
if ($materials || $isteacher) {
    echo html_writer::start_div('livecourse-materials mb-4');
    echo html_writer::tag('h3', get_string('coursematerials', 'mod_livecourse'));
    if (!$materials) {
        echo html_writer::div(get_string('nomaterials', 'mod_livecourse'), 'text-muted');
    }
    foreach ($materials as $material) {
        $classes = 'livecourse-material-card' . ($material->visible ? '' : ' livecourse-material-hidden');
        echo html_writer::start_div($classes);
        echo html_writer::tag('h4', format_string($material->title));
        if (!empty($material->description)) {
            echo html_writer::div(format_text($material->description, FORMAT_PLAIN), 'livecourse-material-description');
        }
        $embedurl = $material->materialtype === 'video' ? livecourse_get_youtube_embed_url($material->url) : null;
        if ($embedurl) {
            echo html_writer::tag('iframe', '', [
                'src' => $embedurl,
                'class' => 'livecourse-material-video',
                'allow' => 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture',
                'allowfullscreen' => 'allowfullscreen',
                'title' => format_string($material->title),
            ]);
        }
        echo html_writer::link($material->url, get_string('openmaterial', 'mod_livecourse'), [
            'class' => 'btn btn-outline-primary mt-2',
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ]);
        if ($isteacher) {
            echo html_writer::start_div('livecourse-material-actions mt-2');
            foreach (['togglematerial', 'deletematerial'] as $action) {
                echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/mod/livecourse/manage.php')]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'materialid', 'value' => $material->id]);
                $label = $action === 'togglematerial'
                    ? ($material->visible ? get_string('hidematerial', 'mod_livecourse') : get_string('showmaterial', 'mod_livecourse'))
                    : get_string('deletematerial', 'mod_livecourse');
                echo html_writer::tag('button', $label, ['class' => 'btn btn-sm btn-secondary', 'type' => 'submit']);
                echo html_writer::end_tag('form');
            }
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}
