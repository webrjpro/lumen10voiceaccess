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
 * classes/navigation.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess;
defined('MOODLE_INTERNAL') || die();

/** Moodle-derived navigation for the session owner. The router may use consented course labels. */
final class navigation {
    public static function snapshot(int $userid, \stdClass $course, int $cmid = 0): array {
        global $CFG, $DB, $USER;
        // The native enrolment/calendar APIs below evaluate the current session, not an arbitrary user.
        if ($userid <= 0 || $userid !== (int)($USER->id ?? 0)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'VoiceAccess');
        }
        require_once($CFG->libdir . '/enrollib.php');
        $firstname = '';
        if (!empty($USER->id) && (int)$USER->id === $userid && !empty($USER->firstname)) {
            $firstname = clean_param($USER->firstname, PARAM_TEXT);
        } else if ($u = $DB->get_record('user', ['id' => $userid], 'firstname')) {
            $firstname = clean_param((string)$u->firstname, PARAM_TEXT);
        }
        $result = [
            'username' => $firstname,
            'coursename' => (int)$course->id !== SITEID ? clean_param(format_string($course->fullname), PARAM_TEXT) : '',
            'courses' => [],
            'activities' => [],
            'sections' => [],
            'grades' => [],
            'whatsnew' => [],
            'currentcm' => $cmid,
        ];
        try {
            if ($DB->get_manager()->table_exists('notifications')) {
                $notifs = $DB->get_records_select(
                    'notifications',
                    'useridto = :uid AND timeread IS NULL',
                    ['uid' => $userid],
                    'timecreated DESC',
                    'id,subject',
                    0,
                    5
                );
                foreach ($notifs as $n) {
                    $subj = trim(clean_param((string)$n->subject, PARAM_TEXT));
                    if ($subj !== '') {
                        $result['whatsnew'][] = 'Notificação: ' . $subj;
                    }
                }
            }
            if ($DB->get_manager()->table_exists('event')) {
                require_once($CFG->dirroot . '/calendar/lib.php');
                $now = time();
                $events = $DB->get_records_select(
                    'event',
                    'visible = 1 AND (userid = :uid OR courseid = :cid OR eventtype = :site) AND timestart >= :now AND timestart <= :limit',
                    ['uid' => $userid, 'cid' => (int)$course->id, 'site' => 'site', 'now' => $now - 3600, 'limit' => $now + 7 * 86400],
                    'timestart ASC',
                    '*',
                    0,
                    50
                );
                $eventcount = 0;
                foreach ($events as $ev) {
                    // Course membership alone does not authorize another group's event or a hidden activity.
                    if (!calendar_view_event_allowed(new \calendar_event($ev))) {
                        continue;
                    }
                    // The calendar's group branch precedes its module branch. An own-group override
                    // must still respect the activity's availability for this student.
                    if (!empty($ev->modulename)) {
                        $eventcourseid = (int)($ev->courseid ?? 0);
                        if (!$eventcourseid) {
                            $eventcm = get_coursemodule_from_instance($ev->modulename, (int)$ev->instance,
                                0, false, IGNORE_MISSING);
                            if (!$eventcm) {
                                continue;
                            }
                            $eventcourseid = (int)$eventcm->course;
                        }
                        $instances = get_fast_modinfo($eventcourseid, $userid)->instances;
                        if (empty($instances[$ev->modulename][$ev->instance]->uservisible)) {
                            continue;
                        }
                    }
                    $ename = trim(clean_param(format_string((string)$ev->name), PARAM_TEXT));
                    if ($ename !== '') {
                        $result['whatsnew'][] = 'Evento próximo: ' . $ename;
                        if (++$eventcount >= 5) {
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Optional summary data must never interrupt navigation.
        }
        $enrolled = enrol_get_my_courses('id,fullname', null, 100);
        foreach ($enrolled as $item) {
            $cname = clean_param(format_string($item->fullname, true,
                ['context' => \context_course::instance($item->id)]), PARAM_TEXT);
            $result['courses'][] = [
                'name' => $cname,
                'url' => (new \moodle_url('/course/view.php', ['id' => $item->id]))->out(false),
            ];
            $gitem = $DB->get_record('grade_items', ['courseid' => $item->id, 'itemtype' => 'course', 'hidden' => 0]);
            if ($gitem) {
                $gval = $DB->get_record('grade_grades', ['itemid' => $gitem->id, 'userid' => $userid, 'hidden' => 0]);
                if ($gval && $gval->finalgrade !== null) {
                    $result['grades'][] = [
                        'course' => $cname,
                        'grade' => round((float)$gval->finalgrade, 1),
                        'max' => round((float)$gitem->grademax, 1),
                    ];
                }
            }
        }
        if ((int)$course->id === SITEID) {
            return $result;
        }
        $info = get_fast_modinfo($course, $userid);
        foreach ($info->get_section_info_all() as $sec) {
            if (!$sec->uservisible) {
                continue;
            }
            $secname = trim(clean_param(get_section_name($course, $sec), PARAM_TEXT));
            if ($secname !== '') {
                $result['sections'][] = ['section' => (int)$sec->section, 'name' => $secname];
            }
        }
        foreach ($info->get_sections() as $section => $ids) {
            foreach ($ids as $id) {
                $cm = $info->get_cm($id);
                if (!$cm->uservisible || !$cm->has_view() || !$cm->url) {
                    continue;
                }
                $result['activities'][] = [
                    'id' => (int)$cm->id, 'name' => clean_param(format_string($cm->name, true,
                        ['context' => $cm->context]), PARAM_TEXT),
                    'url' => $cm->url->out(false), 'section' => (int)$section, 'type' => $cm->modname,
                ];
                if (count($result['activities']) >= 250) {
                    return $result;
                }
            }
        }
        return $result;
    }
}
