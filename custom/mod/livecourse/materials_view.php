<?php
defined('MOODLE_INTERNAL') || die();

$isteacher = has_capability('mod/livecourse:manage', $context);
$materialconditions = ['livecourseid' => $livecourse->id];
if (!$isteacher) {
    $materialconditions['visible'] = 1;
}
$materials = $DB->get_records('livecourse_material', $materialconditions, 'sortorder, id');
if ($materials || $isteacher) {
    echo html_writer::start_div('livecourse-materials mb-4', [
        'data-material-sortable' => $isteacher ? '1' : '0',
        'data-manage-url' => (new moodle_url('/mod/livecourse/manage.php'))->out(false),
        'data-cmid' => $cm->id,
        'data-sesskey' => sesskey(),
    ]);
    echo html_writer::tag('h3', get_string('coursematerials', 'mod_livecourse'));
    if ($isteacher && $materials) {
        echo html_writer::div(get_string('dragmaterialhelp', 'mod_livecourse'), 'livecourse-sort-help');
    }
    if (!$materials) {
        echo html_writer::div(get_string('nomaterials', 'mod_livecourse'), 'text-muted');
    }
    foreach ($materials as $material) {
        $classes = 'livecourse-material-card' . ($material->visible ? '' : ' livecourse-material-hidden');
        echo html_writer::start_div($classes, [
            'data-material-card' => '1',
            'data-material-id' => $material->id,
            'draggable' => $isteacher ? 'true' : 'false',
        ]);
        echo html_writer::start_div('livecourse-material-header');
        if ($isteacher) {
            echo html_writer::span('⋮⋮', 'livecourse-drag-handle', [
                'title' => get_string('dragmaterial', 'mod_livecourse'),
                'aria-label' => get_string('dragmaterial', 'mod_livecourse'),
            ]);
        }
        echo html_writer::tag('h4', format_string($material->title));
        echo html_writer::span(get_string('material' . $material->materialtype, 'mod_livecourse'), 'badge bg-light text-dark');
        echo html_writer::end_div();
        if (!empty($material->description)) {
            echo html_writer::div(format_text($material->description, FORMAT_PLAIN), 'livecourse-material-description');
        }
        if ($material->materialtype === 'page' && !empty($material->content)) {
            echo html_writer::div(format_text($material->content, FORMAT_HTML, ['context' => $context]), 'livecourse-page-content');
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
        if ($material->materialtype !== 'page') {
            echo html_writer::link($material->url, get_string('openmaterial', 'mod_livecourse'), [
                'class' => 'btn btn-outline-primary mt-2',
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ]);
        }
        if ($isteacher) {
            echo html_writer::start_div('livecourse-material-actions mt-2');
            foreach (['showmaterial', 'togglematerial', 'deletematerial'] as $action) {
                $formattributes = ['method' => 'post', 'action' => new moodle_url('/mod/livecourse/manage.php')];
                if ($action === 'showmaterial') {
                    $formattributes['class'] = 'livecourse-realtime-form';
                }
                echo html_writer::start_tag('form', $formattributes);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'materialid', 'value' => $material->id]);
                $label = match ($action) {
                    'showmaterial' => get_string('showlive', 'mod_livecourse'),
                    'togglematerial' => $material->visible
                        ? get_string('hidematerial', 'mod_livecourse')
                        : get_string('showmaterial', 'mod_livecourse'),
                    default => get_string('deletematerial', 'mod_livecourse'),
                };
                $class = $action === 'showmaterial' ? 'btn btn-sm btn-success' : 'btn btn-sm btn-secondary';
                $attributes = ['class' => $class, 'type' => 'submit'];
                if ($action === 'deletematerial') {
                    $attributes['data-confirm-delete'] = get_string('confirmdeletematerial', 'mod_livecourse');
                }
                echo html_writer::tag('button', $label, $attributes);
                echo html_writer::end_tag('form');
            }
            echo html_writer::end_div();

            $suffix = (string) $material->id;
            if ($material->materialtype === 'page') {
                echo html_writer::link(new moodle_url('/mod/livecourse/page.php', [
                    'id' => $cm->id, 'materialid' => $material->id,
                ]), get_string('editcontentpage', 'mod_livecourse'), ['class' => 'btn btn-outline-primary mt-3']);
                echo html_writer::end_div();
                continue;
            }
            echo html_writer::start_tag('details', ['class' => 'livecourse-material-editor']);
            echo html_writer::tag('summary', get_string('editmaterial', 'mod_livecourse'));
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => new moodle_url('/mod/livecourse/manage.php'),
                'data-material-editor' => '1',
            ]);
            foreach ([
                'id' => $cm->id,
                'sesskey' => sesskey(),
                'action' => 'editmaterial',
                'materialid' => $material->id,
            ] as $name => $value) {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
            }
            echo html_writer::tag('label', get_string('materialtitle', 'mod_livecourse'), ['for' => 'edit-title-' . $suffix]);
            echo html_writer::empty_tag('input', [
                'id' => 'edit-title-' . $suffix,
                'name' => 'materialtitle',
                'value' => $material->title,
                'required' => true,
                'class' => 'form-control mb-2',
            ]);
            echo html_writer::tag('label', get_string('materialtype', 'mod_livecourse'), ['for' => 'edit-type-' . $suffix]);
            echo html_writer::select([
                'video' => get_string('materialvideo', 'mod_livecourse'),
                'document' => get_string('materialdocument', 'mod_livecourse'),
                'link' => get_string('materiallink', 'mod_livecourse'),
            ], 'materialtype', $material->materialtype, false, [
                'id' => 'edit-type-' . $suffix,
                'class' => 'form-select mb-2',
                'data-edit-material-type' => '1',
            ]);
            echo html_writer::start_div('', ['data-edit-material-url' => '1']);
            echo html_writer::tag('label', get_string('materialurl', 'mod_livecourse'), ['for' => 'edit-url-' . $suffix]);
            echo html_writer::empty_tag('input', [
                'id' => 'edit-url-' . $suffix,
                'name' => 'materialurl',
                'type' => 'url',
                'value' => $material->url,
                'class' => 'form-control mb-2',
            ]);
            echo html_writer::end_div();
            echo html_writer::tag('label', get_string('materialdescription', 'mod_livecourse'), ['for' => 'edit-description-' . $suffix]);
            echo html_writer::tag('textarea', s($material->description ?? ''), [
                'id' => 'edit-description-' . $suffix,
                'name' => 'materialdescription',
                'class' => 'form-control mb-2',
            ]);
            echo html_writer::start_div('', ['data-edit-material-page' => '1']);
            echo html_writer::tag('label', get_string('materialcontent', 'mod_livecourse'), ['for' => 'edit-content-' . $suffix]);
            echo html_writer::tag('textarea', s($material->content ?? ''), [
                'id' => 'edit-content-' . $suffix,
                'name' => 'materialcontent',
                'class' => 'form-control mb-2',
                'rows' => 7,
            ]);
            echo html_writer::end_div();
            echo html_writer::tag('button', get_string('savematerialchanges', 'mod_livecourse'), [
                'class' => 'btn btn-primary', 'type' => 'submit',
            ]);
            echo html_writer::end_tag('form');
            echo html_writer::end_tag('details');
        }
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}
