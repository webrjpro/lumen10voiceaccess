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
 * classes/learning_flow.php.
 *
 * @package mod_lumen10voiceaccess
 * @copyright 2026 Carlos Antonio de Oliveira Piquet
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_lumen10voiceaccess;

defined('MOODLE_INTERNAL') || die();

/** A current-user learning journey; visibility, deadlines and completion remain owned by Moodle. */
final class learning_flow {
    private static bool $deadlineunavailable = false;

    public static function deadline_unavailable(): bool {
        return self::$deadlineunavailable;
    }
    public static function enabled(): bool {
        global $USER;
        return isloggedin() && !isguestuser() && !is_siteadmin() &&
            !has_capability('mod/lumen10voiceaccess:manage', \context_system::instance()) &&
            (bool)get_config('mod_lumen10voiceaccess', 'version') && access::allowed((int)$USER->id);
    }

    public static function url(string $view = 'panel', int $courseid = 0): \moodle_url {
        $params = ['view' => $view];
        if ($courseid) {
            $params['id'] = $courseid;
        }
        return new \moodle_url('/mod/lumen10voiceaccess/learn.php', $params);
    }

    /** Only full GET entry pages are redirected, never an assessment request or an editing action. */
    public static function entry_target(string $pagetype, int $courseid = 0): ?\moodle_url {
        if (in_array($pagetype, ['my-index', 'my-indexsys', 'site-index'], true)) {
            return self::url();
        }
        if ($pagetype === 'my-courses') {
            return self::url('courses');
        }
        if (str_starts_with($pagetype, 'course-view-') && $courseid > SITEID) {
            return self::url('course', $courseid);
        }
        return null;
    }

    public static function supports_page(\moodle_page $page): bool {
        $studentpages = ['/grade/report/overview/index.php', '/grade/report/user/index.php', '/calendar/view.php',
            '/message/index.php', '/message/output/popup/notifications.php', '/user/profile.php', '/user/preferences.php'];
        $studentpages = array_map(static fn(string $path): string => (new \moodle_url($path))->get_path(), $studentpages);
        return !in_array($page->pagelayout, ['embedded', 'popup', 'maintenance', 'print', 'secure', 'redirect'], true) &&
            !$page->user_is_editing() &&
            (str_starts_with($page->pagetype, 'mod-') || str_starts_with($page->pagetype, 'course-view-') ||
                in_array($page->url->get_path(), $studentpages, true) ||
                in_array($page->pagetype, ['mod-lumen10voiceaccess-learn', 'mod-lumen10voiceaccess-preferences',
                    'my-index', 'my-indexsys', 'my-courses', 'site-index'], true));
    }

    /** Active enrolments only: an empty enrolment list must never become a public course catalogue. */
    public static function courses(): array {
        global $USER, $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');
        $courses = enrol_get_users_courses($USER->id, true, 'id,fullname,visible,enablecompletion,sortorder,format', 'sortorder ASC');
        $accesses = $DB->get_records('user_lastaccess', ['userid' => $USER->id], '', 'courseid,timeaccess');
        foreach ($courses as $course) {
            $course->l10valastaccess = (int)($accesses[$course->id]->timeaccess ?? 0);
        }
        uasort($courses, static fn($a, $b) => ($b->l10valastaccess <=> $a->l10valastaccess) ?:
            ($a->sortorder <=> $b->sortorder) ?: ($a->id <=> $b->id));
        return $courses;
    }

    public static function course_name(\stdClass $course): string {
        return clean_param(format_string($course->fullname, true,
            ['context' => \context_course::instance($course->id)]), PARAM_TEXT);
    }

    /** Activities follow the teacher's section order and native availability rules. */
    public static function activities(\stdClass $course): array {
        global $USER, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $info = get_fast_modinfo($course, $USER->id);
        $completion = new \completion_info($course);
        $items = [];
        foreach ($info->get_sections() as $section => $ids) {
            if (!$info->get_section_info($section)->uservisible) {
                continue;
            }
            foreach ($ids as $id) {
                $cm = $info->get_cm($id);
                if (!$cm->uservisible || !$cm->has_view() || !$cm->url) {
                    continue;
                }
                $data = $completion->get_data($cm, false, $USER->id);
                $items[] = [
                    'id' => (int)$cm->id, 'name' => clean_param(format_string($cm->name, true,
                        ['context' => $cm->context]), PARAM_TEXT),
                    'url' => $cm->url->out(false), 'type' => $cm->modname,
                    'section' => (int)$section,
                    'sectionname' => clean_param(get_section_name($course, $info->get_section_info($section)), PARAM_TEXT),
                    'complete' => in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true),
                    'tracked' => (bool)$completion->is_enabled($cm),
                ];
            }
        }
        return $items;
    }

    /** One ordered list: unfinished attempts, actionable deadlines, then the most recent course. */
    public static function priorities(?int $now = null): array {
        global $USER, $DB, $CFG;
        $now = $now ?? time();
        self::$deadlineunavailable = false;
        $courses = self::courses();
        if (!$courses) {
            return [];
        }
        // Defines CALENDAR_EVENT_TYPE_ACTION for entry pages that have not loaded calendar blocks.
        require_once($CFG->dirroot . '/calendar/lib.php');
        $items = [];
        $seen = [];
        [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'course');
        $params += ['userid' => $USER->id, 'progress' => 'inprogress', 'overdue' => 'overdue'];
        $attempts = $DB->get_records_sql("SELECT qa.id,qa.quiz,qa.timemodified,q.course
              FROM {quiz_attempts} qa JOIN {quiz} q ON q.id=qa.quiz
             WHERE qa.userid=:userid AND qa.preview=0 AND qa.state IN (:progress,:overdue) AND q.course $insql
             ORDER BY qa.timemodified DESC,qa.id DESC", $params, 0, 20);
        foreach ($attempts as $attempt) {
            $cm = get_fast_modinfo($courses[$attempt->course], $USER->id)->get_instances_of('quiz')[$attempt->quiz] ?? null;
            if (!$cm || !$cm->uservisible || !has_capability('mod/quiz:attempt', $cm->context)) {
                continue;
            }
            $seen[$cm->id] = true;
            $items[] = self::task($cm, $courses[$attempt->course], 0, (int)$attempt->timemodified,
                get_string('flowattempt', preferences::COMPONENT));
        }
        try {
            // This API applies participant/group visibility and the module's per-user action callback.
            $events = \core_calendar\local\api::get_action_events_by_timesort($now - 7 * DAYSECS,
                $now + 7 * DAYSECS, null, 50, true, $USER);
            foreach ($events as $event) {
                $action = $event->get_action();
                $proxy = $event->get_course_module();
                $courseid = $event->get_course() ? (int)$event->get_course()->get('id') : 0;
                if (!$proxy || !isset($courses[$courseid]) || !$action || !$action->is_actionable() ||
                        $action->get_item_count() < 1) {
                    continue;
                }
                $cmid = (int)$proxy->get('id');
                $cm = get_fast_modinfo($courses[$courseid], $USER->id)->get_cm($cmid);
                if (!$cm->uservisible || !$cm->has_view() || !$cm->url || isset($seen[$cmid])) {
                    continue;
                }
                $due = $event->get_times()->get_sort_time()->getTimestamp();
                // Opening events are not deadlines. Keep the native activity link as the safe entry point.
                if (!in_array($event->get_type(), ['due', 'close'], true)) {
                    continue;
                }
                $seen[$cmid] = true;
                $items[] = self::task($cm, $courses[$courseid], 1, $due,
                    get_string($due < $now ? 'flowoverdue' : 'flowdue', preferences::COMPONENT,
                        userdate($due, get_string('strftimedatetimeshort', 'langconfig'))));
            }
        } catch (\Throwable $e) {
            // Report unavailable deadline data without substituting unfiltered calendar events.
            self::$deadlineunavailable = true;
            error_log('VoiceAccess deadline service unavailable: ' . get_class($e));
        }
        usort($items, static fn($a, $b) => ($a['priority'] <=> $b['priority']) ?:
            ($a['sort'] <=> $b['sort']) ?: ($a['id'] <=> $b['id']));
        foreach ($courses as $course) {
            foreach (self::activities($course) as $activity) {
                if ($activity['complete'] || isset($seen[$activity['id']])) {
                    continue;
                }
                $activity += ['coursename' => self::course_name($course), 'priority' => 2, 'sort' => 0,
                    'reason' => get_string('flowcontinue', preferences::COMPONENT)];
                $items[] = $activity;
                break 2;
            }
        }
        return array_slice($items, 0, 6);
    }

    private static function task(\cm_info $cm, \stdClass $course, int $priority, int $sort, string $reason): array {
        return ['id' => (int)$cm->id, 'name' => clean_param(format_string($cm->name, true,
            ['context' => $cm->context]), PARAM_TEXT), 'url' => $cm->url->out(false),
            'coursename' => self::course_name($course), 'priority' => $priority, 'sort' => $sort,
            'reason' => $reason, 'type' => $cm->modname];
    }

    /** Navigation is derived from visible server-rendered actions, so spoken numbering matches the page. */
    public static function navigation(\moodle_page $page): array {
        global $USER;
        $course = $page->course;
        $result = ['simple' => true, 'username' => clean_param($USER->firstname, PARAM_TEXT),
            'coursename' => (int)$course->id !== (int)SITEID ? self::course_name($course) : '',
            'courses' => [], 'activities' => [], 'sections' => [], 'grades' => [], 'whatsnew' => [],
            'currentcm' => (int)($page->cm->id ?? 0),
            'routes' => ['OPEN_DASHBOARD' => self::url()->out(false), 'OPEN_COURSES' => self::url('courses')->out(false),
                'HELP' => self::url('help')->out(false)]];
        foreach (self::courses() as $item) {
            $result['courses'][] = ['name' => self::course_name($item), 'url' => self::url('course', $item->id)->out(false)];
        }
        if ((int)$course->id !== (int)SITEID) {
            $result['activities'] = self::activities($course);
        } else if ($page->pagetype === 'mod-lumen10voiceaccess-learn' &&
                ($page->url->get_param('view') ?: 'panel') === 'panel') {
            // The numbered spoken choices must match the visible priorities, including an owned ongoing quiz.
            $result['activities'] = self::priorities();
        }
        return $result;
    }
}
