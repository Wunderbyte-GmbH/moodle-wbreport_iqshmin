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
 * Wunderbyte table for the IQSHMIN statistics report.
 *
 * @package     wbreport_iqshmin
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace wbreport_iqshmin\local\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use local_wunderbyte_table\wunderbyte_table;
use local_wunderbyte_table\local\customfield\wbt_field_controller_info;

/**
 * Wunderbyte table subclass for the IQSHMIN statistics report.
 *
 * @package     wbreport_iqshmin
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class iqshmin_table extends wunderbyte_table {
    /**
     * Unix timestamp set by the report output class at table-construction time.
     * Used to write a "Generated at" footer line in PDF exports.
     *
     * @var int
     */
    public int $generatedat = 0;

    /**
     * Override is_downloading to set a report-specific filename with timestamp.
     *
     * @param string|null $download dataformat type, or null to query current state.
     * @param string $filename ignored – generated from the report identifier.
     * @param string $sheettitle sheet title passed through unchanged.
     * @return string current download dataformat type.
     */
    public function is_downloading($download = null, $filename = '', $sheettitle = '') {
        if ($download !== null) {
            $timestamp = date('Ymd_Hi');
            $filename  = "iqshmin_{$timestamp}";
        }
        return parent::is_downloading($download, $filename, $sheettitle);
    }

    /**
     * Override export_class_instance to inject our custom export class for PDF exports.
     *
     * @param \core_table\dataformat_export_format|null $exportclass Optional export class to set.
     * @return \core_table\dataformat_export_format
     */
    public function export_class_instance($exportclass = null) {
        if (is_null($exportclass) && is_null($this->exportclass) && $this->download === 'pdf') {
            $this->exportclass = new \wbreport_iqshmin\local\export\iqshmin_export_format($this, $this->download);
            $this->exportclass->table = $this;
            if (!$this->exportclass->document_started()) {
                $this->exportclass->start_document($this->filename, $this->sheettitle);
            }
            return $this->exportclass;
        }
        return parent::export_class_instance($exportclass);
    }

    /**
     * Finish output: write a "Generated at" footer line for PDF exports.
     *
     * @param bool   $closeexportclassdoc Whether to close and stream the document.
     * @param string $encodedtable        Encoded table hash (HTML path only).
     * @return \local_wunderbyte_table\output\table|void
     */
    public function finish_output($closeexportclassdoc = true, $encodedtable = '') {
        if ($this->exportclass !== null) {
            $this->exportclass->finish_table();
            if ($this->generatedat > 0 && method_exists($this->exportclass, 'write_footer_text')) {
                global $USER;
                $label    = get_string('generated_at', 'wbreport_iqshmin');
                $datestr  = userdate($this->generatedat, get_string('strftimedatetime', 'langconfig'));
                $fullname = $USER->firstname . ' ' . $USER->lastname;
                $this->exportclass->write_footer_text("{$label}: {$datestr} – {$fullname} ({$USER->email})");
            }
            if ($closeexportclassdoc) {
                $this->exportclass->finish_document();
            }
            return;
        }
        return parent::finish_output($closeexportclassdoc, $encodedtable);
    }

    /**
     * Format the optionname column as a link to optionview.php (HTML only, not on download).
     *
     * @param object $values
     * @return string
     */
    public function col_optionname($values): string {
        $name = s($values->optionname);
        if ($this->is_downloading() || empty($values->cmid) || empty($values->optionid)) {
            return $name;
        }
        $url = new \moodle_url('/mod/booking/view.php', [
            'id'         => (int) $values->cmid,
            'optionid'   => (int) $values->optionid,
            'whichview'  => 'showonlyone',
        ]);
        return '<a href="' . $url->out(false) . '">' . $name . '</a>';
    }

    /**
     * Format the teacher column.
     *
     * On download: "Firstname Lastname (email@example.com), ..." (export format).
     * On HTML: comma-separated links to user profiles.
     *
     * @param object $values
     * @return string
     */
    public function col_teacher($values): string {
        if (empty($values->teacher)) {
            return '';
        }
        if ($this->is_downloading()) {
            return $values->teacher_export ?? $values->teacher;
        }
        $names = array_map('trim', explode(', ', $values->teacher));
        $ids   = array_map('trim', explode(',', $values->teacher_userids ?? ''));
        $links = [];
        foreach ($names as $i => $name) {
            if ($name === '') {
                continue;
            }
            $uid = (int) ($ids[$i] ?? 0);
            if ($uid > 0) {
                $url     = new \moodle_url('/user/profile.php', ['id' => $uid]);
                $links[] = '<a href="' . $url->out(false) . '">' . s($name) . '</a>';
            } else {
                $links[] = s($name);
            }
        }
        return implode(', ', $links);
    }

    /**
     * Format the coursestarttime column as a human-readable date.
     *
     * @param object $values
     * @return string
     */
    public function col_coursestarttime($values): string {
        if (empty($values->coursestarttime)) {
            return '';
        }
        if ($this->is_downloading()) {
            return date('Y-m-d H:i', $values->coursestarttime);
        }
        return userdate($values->coursestarttime, get_string('strftimedatetime', 'langconfig'));
    }

    /**
     * Format the courseendtime column as a human-readable date.
     *
     * @param object $values
     * @return string
     */
    public function col_courseendtime($values): string {
        if (empty($values->courseendtime)) {
            return '';
        }
        if ($this->is_downloading()) {
            return date('Y-m-d H:i', $values->courseendtime);
        }
        return userdate($values->courseendtime, get_string('strftimedatetime', 'langconfig'));
    }

    /**
     * Format the schulart column via the customfield field controller.
     *
     * @param object $values
     * @return string
     */
    public function col_schulart($values): string {
        return $this->render_customfield_value($values->schulart ?? '', 'schulart');
    }

    /**
     * Format the fach column via the customfield field controller.
     *
     * @param object $values
     * @return string
     */
    public function col_fach($values): string {
        return $this->render_customfield_value($values->fach ?? '', 'fach');
    }

    /**
     * Format the kategorie column via the customfield field controller.
     *
     * @param object $values
     * @return string
     */
    public function col_kategorie($values): string {
        return $this->render_customfield_value($values->kategorie ?? '', 'kategorie');
    }

    /**
     * Format the stunden column with one decimal place.
     *
     * @param object $values
     * @return string
     */
    public function col_stunden($values): string {
        if (!isset($values->stunden) || $values->stunden === null || $values->stunden === '') {
            return '';
        }
        if ((float) $values->stunden == 0.0) {
            return '0.0';
        }
        return number_format((float) $values->stunden, 1);
    }

    /**
     * Format the bookedusers column as an integer.
     *
     * @param object $values
     * @return string
     */
    public function col_bookedusers($values): string {
        return (string) (int) ($values->bookedusers ?? 0);
    }

    /**
     * Format the minanswers column as an integer.
     *
     * @param object $values
     * @return string
     */
    public function col_minanswers($values): string {
        return (string) (int) ($values->minanswers ?? 0);
    }

    /**
     * Render a customfield value (single or comma-separated keys) via the
     * wunderbyte_table customfield helper.
     *
     * @param string $rawvalue
     * @param string $shortname
     * @return string
     */
    private function render_customfield_value(string $rawvalue, string $shortname): string {
        if ($rawvalue === '') {
            return '';
        }
        $fieldcontroller = wbt_field_controller_info::get_instance_by_shortname($shortname);
        if (!$fieldcontroller) {
            return s($rawvalue);
        }
        if (str_contains($rawvalue, ',')) {
            $values = [];
            foreach (explode(',', $rawvalue) as $key) {
                $values[] = $fieldcontroller->get_option_value_by_key($key);
            }
            return implode(', ', $values);
        }
        return $fieldcontroller->get_option_value_by_key($rawvalue);
    }
}
