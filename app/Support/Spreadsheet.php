<?php

namespace App\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Builds an .xlsx download one sheet at a time. Floats are written as numbers with
 * two decimals so they can be summed in Excel; strings stay text, so phone numbers
 * keep their leading zero.
 */
class Spreadsheet
{
    private array $sheets = [];

    public static function make(): static
    {
        return new static;
    }

    /**
     * @param  list<string>  $headings
     * @param  iterable<list<mixed>>  $rows
     */
    public function sheet(string $name, array $headings, iterable $rows): static
    {
        $this->sheets[] = compact('name', 'headings', 'rows');

        return $this;
    }

    public function download(string $filename): BinaryFileResponse
    {
        $options = new Options;
        $options->DEFAULT_COLUMN_WIDTH = 16;

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer = new Writer($options);
        $writer->openToFile($path);

        $heading = (new Style)->setFontBold();
        $money = (new Style)->setFormat('#,##0.00');

        foreach ($this->sheets as $index => $sheet) {
            ($index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent())->setName($sheet['name']);
            $writer->addRow(Row::fromValues($sheet['headings'], $heading));

            foreach ($sheet['rows'] as $row) {
                $writer->addRow(new Row(array_map(
                    fn ($value) => Cell::fromValue($value, is_float($value) ? $money : null),
                    array_values($row),
                )));
            }
        }

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
