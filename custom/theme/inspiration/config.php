<?php

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'inspiration';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->usefallback = true;
$THEME->scss = function($theme) {
    return theme_inspiration_get_main_scss_content($theme);
};
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->enable_dock = false;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = ['notitle' => true];
