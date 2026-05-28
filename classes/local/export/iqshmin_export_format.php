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
 * Custom dataformat export class for the IQSHMIN report.
 *
 * @package     wbreport_iqshmin
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace wbreport_iqshmin\local\export;

/**
 * Extends the core dataformat export class to support writing a footer line
 * (e.g. a "Generated at" timestamp) into PDF exports.
 *
 * @package     wbreport_iqshmin
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class iqshmin_export_format extends \core_table\dataformat_export_format {
    /**
     * Write a line of plain text after all table rows have been written.
     *
     * Only has an effect when the underlying dataformat is the PDF writer.
     * Uses reflection to access the protected {@see \dataformat_pdf\writer::$pdf}
     * TCPDF instance so that the footer can be appended without modifying any
     * file outside the wbreport_iqshmin plugin.
     *
     * @param string $text Plain text to append at the bottom of the PDF.
     */
    public function write_footer_text(string $text): void {
        if (!($this->dataformat instanceof \dataformat_pdf\writer)) {
            return;
        }
        $rfpdf = new \ReflectionProperty(\dataformat_pdf\writer::class, 'pdf');
        $rfpdf->setAccessible(true);
        /** @var \pdf $pdf */
        $pdf = $rfpdf->getValue($this->dataformat);

        $margins   = $pdf->getMargins();
        $pagewidth = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
        $pdf->Ln(5);
        $fontfamily = $pdf->getFontFamily();
        $pdf->SetFont($fontfamily, '', 9);
        $pdf->MultiCell($pagewidth, 0, $text, 0, 'L');
    }
}
