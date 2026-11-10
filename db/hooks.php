<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * db/hooks.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$callbacks = [[
    'hook' => \core\hook\output\before_footer_html_generation::class,
    'callback' => [\mod_lumen10voiceaccess\hook_listener::class, 'footer'],
], [
    'hook' => \core\hook\after_config::class,
    'callback' => [\mod_lumen10voiceaccess\login_policy::class, 'configure'],
], [
    'hook' => \core\hook\output\before_http_headers::class,
    'callback' => [\mod_lumen10voiceaccess\hook_listener::class, 'learning_headers'],
], [
    'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
    'callback' => [\mod_lumen10voiceaccess\hook_listener::class, 'learning_header'],
]];

