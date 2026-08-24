<?php

defined('MOODLE_INTERNAL') || die();

function theme_inspiration_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';

    $boostscss = $CFG->dirroot . '/theme/boost/scss/preset/default.scss';

    if (file_exists($boostscss)) {
        $scss .= file_get_contents($boostscss);
    }

    $customscss = __DIR__ . '/scss/custom.scss';

    if (file_exists($customscss)) {
        $scss .= "\n";
        $scss .= file_get_contents($customscss);
    }

    return $scss;
}
