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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Output class for the IQSHMIN statistics report.
 *
 * @package     wbreport_iqshmin
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace wbreport_iqshmin\output;

use cache_helper;
use local_wb_reports\plugininfo\wbreport;
use local_wb_reports\plugininfo\wbreport_interface;
use local_wunderbyte_table\filters\types\standardfilter;
use stdClass;
use renderer_base;
use renderable;
use templatable;
use wbreport_iqshmin\local\table\iqshmin_table;

/**
 * Prepares data for the IQSHMIN statistics report.
 *
 * One row per booking_option whose number of active bookings
 * (booking_answers with waitinglist = 0) is below the option's minanswers value.
 *
 * Columns: optionname, optiondates, schulart, fach, kategorie, stunden,
 *          bookedusers, minanswers.
 *
 * @package     wbreport_iqshmin
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class iqshmin implements renderable, templatable, wbreport_interface {
    /** @var string $tabledata Rendered HTML of the table. */
    private string $tabledata = '';

    /** @var int Unix timestamp captured at construction time. */
    private int $generatedat;

    /**
     * In the constructor, we gather all the data we need.
     */
    public function __construct() {
        global $DB;

        cache_helper::purge_by_event('setbackwbreportscache');

        $table = new iqshmin_table('iqshmin_table');

        $table->define_headers([
            get_string('optionname', 'wbreport_iqshmin'),
            get_string('coursestarttime', 'wbreport_iqshmin'),
            get_string('courseendtime', 'wbreport_iqshmin'),
            get_string('schulart', 'wbreport_iqshmin'),
            get_string('fach', 'wbreport_iqshmin'),
            get_string('kategorie', 'wbreport_iqshmin'),
            get_string('stunden', 'wbreport_iqshmin'),
            get_string('bookedusers', 'wbreport_iqshmin'),
            get_string('minanswers', 'wbreport_iqshmin'),
        ]);

        $table->define_columns([
            'optionname',
            'coursestarttime',
            'courseendtime',
            'schulart',
            'fach',
            'kategorie',
            'stunden',
            'bookedusers',
            'minanswers',
        ]);

        // Snapshot of the current time: shown in the page header and written as PDF footer.
        $now = time();
        $this->generatedat = $now;

        // Fetch the booking module id once via PHP so the main SQL stays free of scalar subqueries.
        $bookingmoduleid = (int) $DB->get_field('modules', 'id', ['name' => 'booking']);

        $fields = "m.*";

        $from = "(
            SELECT
                bo.id                                  AS id,
                bo.id                                  AS optionid,
                bo.text                                AS optionname,
                bo.coursestarttime                     AS coursestarttime,
                bo.courseendtime                       AS courseendtime,
                cm.id                                  AS cmid,
                COALESCE(bo.minanswers, 0)             AS minanswers,
                COALESCE(ans.bookedusers, 0)           AS bookedusers,
                COALESCE(cfd_schulart.value, '')       AS schulart,
                COALESCE(cfd_fach.value, '')           AS fach,
                COALESCE(cfd_kategorie.value, '')      AS kategorie,
                COALESCE(cfd_stunden.decvalue, 0)      AS stunden,
                {$now}                                 AS generated_at
            FROM {booking_options} bo
            LEFT JOIN {course_modules} cm
                ON  cm.instance = bo.bookingid
                AND cm.module   = {$bookingmoduleid}
            LEFT JOIN (
                SELECT ba.optionid, COUNT(*) AS bookedusers
                FROM {booking_answers} ba
                WHERE ba.waitinglist = 0
                GROUP BY ba.optionid
            ) ans
                ON ans.optionid = bo.id
            LEFT JOIN {customfield_field} cff_schulart
                ON  cff_schulart.shortname = 'schulart'
            LEFT JOIN {customfield_category} cfc_schulart
                ON  cfc_schulart.id        = cff_schulart.categoryid
                AND cfc_schulart.component = 'mod_booking'
                AND cfc_schulart.area      = 'booking'
            LEFT JOIN {customfield_data} cfd_schulart
                ON  cfd_schulart.instanceid = bo.id
                AND cfd_schulart.fieldid    = cff_schulart.id
            LEFT JOIN {customfield_field} cff_fach
                ON  cff_fach.shortname = 'fach'
            LEFT JOIN {customfield_category} cfc_fach
                ON  cfc_fach.id        = cff_fach.categoryid
                AND cfc_fach.component = 'mod_booking'
                AND cfc_fach.area      = 'booking'
            LEFT JOIN {customfield_data} cfd_fach
                ON  cfd_fach.instanceid = bo.id
                AND cfd_fach.fieldid    = cff_fach.id
            LEFT JOIN {customfield_field} cff_kategorie
                ON  cff_kategorie.shortname = 'kategorie'
            LEFT JOIN {customfield_category} cfc_kategorie
                ON  cfc_kategorie.id        = cff_kategorie.categoryid
                AND cfc_kategorie.component = 'mod_booking'
                AND cfc_kategorie.area      = 'booking'
            LEFT JOIN {customfield_data} cfd_kategorie
                ON  cfd_kategorie.instanceid = bo.id
                AND cfd_kategorie.fieldid    = cff_kategorie.id
            LEFT JOIN {customfield_field} cff_stunden
                ON  cff_stunden.shortname = 'stunden'
            LEFT JOIN {customfield_category} cfc_stunden
                ON  cfc_stunden.id        = cff_stunden.categoryid
                AND cfc_stunden.component = 'mod_booking'
                AND cfc_stunden.area      = 'booking'
            LEFT JOIN {customfield_data} cfd_stunden
                ON  cfd_stunden.instanceid = bo.id
                AND cfd_stunden.fieldid    = cff_stunden.id
            WHERE COALESCE(ans.bookedusers, 0) < COALESCE(bo.minanswers, 0)
              AND bo.bookingid > 0
        ) m";

        $table->set_filter_sql($fields, $from, '1=1', '', []);

        // Default sort: worst offenders first (fewest bookings).
        $table->sortable(true, 'bookedusers', SORT_ASC);

        $table->define_fulltextsearchcolumns(['optionname', 'schulart', 'fach', 'kategorie']);

        $table->define_sortablecolumns([
            'optionname'      => get_string('optionname', 'wbreport_iqshmin'),
            'coursestarttime' => get_string('coursestarttime', 'wbreport_iqshmin'),
            'courseendtime'   => get_string('courseendtime', 'wbreport_iqshmin'),
            'schulart'    => get_string('schulart', 'wbreport_iqshmin'),
            'fach'        => get_string('fach', 'wbreport_iqshmin'),
            'kategorie'   => get_string('kategorie', 'wbreport_iqshmin'),
            'stunden'     => get_string('stunden', 'wbreport_iqshmin'),
            'bookedusers' => get_string('bookedusers', 'wbreport_iqshmin'),
            'minanswers'  => get_string('minanswers', 'wbreport_iqshmin'),
        ]);

        // Filter: by school type.
        $schulartfilter = new standardfilter('schulart', get_string('schulart', 'wbreport_iqshmin'));
        $table->add_filter($schulartfilter);

        // Filter: by subject.
        $fachfilter = new standardfilter('fach', get_string('fach', 'wbreport_iqshmin'));
        $table->add_filter($fachfilter);

        // Filter: by category.
        $kategoriefilter = new standardfilter('kategorie', get_string('kategorie', 'wbreport_iqshmin'));
        $table->add_filter($kategoriefilter);

        $table->define_cache('local_wb_reports', 'wbreportscache');

        $table->generatedat = $now;

        $table->pageable(true);
        $table->cardsort = true;
        $table->showcountlabel = true;
        $table->showfilterontop = 1;
        $table->showdownloadbutton = true;
        $table->showreloadbutton = true;
        $table->applyfilterondownload = true;
        $table->alloweddownloadformats = ['pdf', 'csv', 'excel'];

        [, , $html] = $table->lazyouthtml(50, true);
        $this->tabledata = $html;
    }

    /**
     * Use this function to render any HTML in the report header.
     *
     * @return string the html for the table header
     */
    public function get_table_header_html(): string {
        $heading = get_string('pluginname', 'wbreport_iqshmin');
        $datestr = userdate($this->generatedat, get_string('strftimedatetime', 'langconfig'));
        $generatedlabel = get_string('generated_at', 'wbreport_iqshmin');
        return '<div class="alert alert-secondary h3">' .
            s($heading) .
            '<div class="small fw-normal mt-1">' .
            s("{$generatedlabel}: {$datestr}") .
            '</div>' .
            '</div>';
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $wbreport = new wbreport();
        $data->dashboardlink = $wbreport->get_dashboard_link();
        $data->tableheader   = $this->get_table_header_html();
        $data->table         = $this->tabledata;
        return $data;
    }
}
