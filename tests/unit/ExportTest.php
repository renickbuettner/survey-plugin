<?php namespace Renick\Survey\Tests\Unit;

use Renick\Survey\Classes\Export\CellSanitizer;
use Renick\Survey\Classes\Export\CsvWriter;
use Renick\Survey\Classes\Export\ResultsExporter;
use Renick\Survey\Classes\Export\XlsxWriter;
use ZipArchive;

class ExportTest extends SurveyTestCase
{
    private array $tmp = [];

    public function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function tmpFile(): string
    {
        return $this->tmp[] = tempnam(sys_get_temp_dir(), 'srvtest');
    }

    private function requireZip(): void
    {
        if (!XlsxWriter::isAvailable()) {
            $this->markTestSkipped('ZipArchive not available');
        }
    }

    /** @return array<string,string> part name => content */
    private function unzip(string $path): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'valid zip');
        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $parts[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();
        return $parts;
    }

    // CellSanitizer

    public function testSanitizerNeutralizesFormulas(): void
    {
        foreach (['=1+1', '+49 123', '-5', '@SUM(A1)', "\tcmd", "\rcmd"] as $v) {
            $this->assertSame("'" . $v, CellSanitizer::neutralizeFormula($v));
        }
        $this->assertSame('hello', CellSanitizer::neutralizeFormula('hello'));
        $this->assertSame('', CellSanitizer::neutralizeFormula(''));
        $this->assertSame('a=b', CellSanitizer::neutralizeFormula('a=b'));
    }

    public function testSanitizerStripsInvalidXmlCharacters(): void
    {
        $this->assertSame("ab\n\tc", CellSanitizer::cleanXml("a\x00b\x0B\n\t\x1Fc"));
        $this->assertSame('Ärger €', CellSanitizer::cleanXml('Ärger €'));
        // broken UTF-8 must not blow up
        $this->assertIsString(CellSanitizer::cleanXml("abc\xFF\xFEdef"));
        $this->assertSame(1, preg_match('//u', CellSanitizer::cleanXml("abc\xFF\xFEdef")));
    }

    public function testSanitizerTruncatesLongText(): void
    {
        $out = CellSanitizer::text(str_repeat('ä', 40000));
        $this->assertSame(CellSanitizer::MAX_CELL_LENGTH, mb_strlen($out, 'UTF-8'));
    }

    // XlsxWriter

    public function testXlsxStructureAndWellFormedXml(): void
    {
        $this->requireZip();
        $w = new XlsxWriter();
        $s = $w->addSheet('Data', [10, 20]);
        $s->addRow(['Head', 'Count'], XlsxWriter::STYLE_HEADER);
        $s->addRow(['A & <B> "q"', 3]);
        $s->addRow(['Share', 0.5], XlsxWriter::STYLE_DEFAULT, [1 => XlsxWriter::STYLE_PERCENT]);
        $w->addSheet('Second');
        $path = $this->tmpFile();
        $w->save($path);

        $this->assertSame('PK', substr(file_get_contents($path), 0, 2));
        $parts = $this->unzip($path);

        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels',
                     'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $required) {
            $this->assertArrayHasKey($required, $parts);
        }
        foreach ($parts as $name => $xml) {
            $dom = new \DOMDocument();
            $this->assertTrue(@$dom->loadXML($xml), "$name is well-formed");
        }

        $sheet = new \DOMDocument();
        $sheet->loadXML($parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('A &amp; &lt;B&gt; "q"', $parts['xl/worksheets/sheet1.xml']);
        $this->assertSame(3, $sheet->getElementsByTagName('row')->length);
        $this->assertStringContainsString('Data', $parts['xl/workbook.xml']);
        $this->assertStringContainsString('Second', $parts['xl/workbook.xml']);
    }

    public function testXlsxCleansSheetNamesAndCellContent(): void
    {
        $this->requireZip();
        $w = new XlsxWriter();
        $s = $w->addSheet('Bad:[name]/*?' . str_repeat('x', 50));
        $s->addRow(["=HYPERLINK(\"http://x\")", "ctrl\x01char", '+49 1']);
        $path = $this->tmpFile();
        $w->save($path);
        $parts = $this->unzip($path);

        $dom = new \DOMDocument();
        $this->assertTrue(@$dom->loadXML($parts['xl/workbook.xml']));
        $name = $dom->getElementsByTagName('sheet')->item(0)->getAttribute('name');
        $this->assertLessThanOrEqual(31, mb_strlen($name));
        $this->assertSame(0, preg_match('/[\[\]:*?\/\\\\]/', $name));

        $xml = $parts['xl/worksheets/sheet1.xml'];
        $this->assertTrue(@(new \DOMDocument())->loadXML($xml));
        $this->assertStringNotContainsString("\x01", $xml);
        $this->assertStringContainsString("'=HYPERLINK", $xml);
        $this->assertStringContainsString("'+49 1", $xml);
        $this->assertStringContainsString('ctrlchar', $xml);
    }

    public function testXlsxWithoutSheetsStillValid(): void
    {
        $this->requireZip();
        $path = $this->tmpFile();
        (new XlsxWriter())->save($path);
        $this->assertArrayHasKey('xl/worksheets/sheet1.xml', $this->unzip($path));
    }

    // CsvWriter

    public function testCsvFallbackFormat(): void
    {
        $w = new CsvWriter();
        $s = $w->addSheet('One');
        $s->addRow(['a;b', 'say "hi"', "line\nbreak", '=1+1']);
        $s->addRow(['x', 2, 0.25], XlsxWriter::STYLE_DEFAULT, [2 => XlsxWriter::STYLE_PERCENT]);
        $s2 = $w->addSheet('Two');
        $s2->addRow(['y']);
        $path = $this->tmpFile();
        $w->save($path);

        $csv = file_get_contents($path);
        $this->assertSame("\xEF\xBB\xBF", substr($csv, 0, 3));
        $this->assertStringContainsString('"a;b";"say ""hi""";"line' . "\n" . 'break";\'=1+1', $csv);
        $this->assertStringContainsString("x;2;25.00%\r\n", $csv);
        $this->assertStringContainsString("One\r\n", $csv);
        $this->assertStringContainsString("Two\r\ny\r\n", $csv);
    }

    // ResultsExporter

    private function exportRows(array $export): array
    {
        $this->tmp[] = $export['path'];
        $this->assertFileExists($export['path']);
        return $export;
    }

    private function sheetCells(string $xml): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $rows = [];
        foreach ($dom->getElementsByTagName('row') as $row) {
            $cells = [];
            foreach ($row->getElementsByTagName('c') as $c) {
                $cells[] = $c->textContent;
            }
            $rows[] = $cells;
        }
        return $rows;
    }

    public function testExporterBuildsWorkbookWithResponsesAndSummary(): void
    {
        $this->requireZip();
        $survey = $this->makeSurvey(['title' => 'Größe & Farbe']);
        $this->makeEvent($survey, ['user_name' => 'Ann', 'user_email' => 'ann@example.com',
            'user_meta' => ['user_phone' => '+49 111', 'user_comment' => '=cmd']], ['Red', 'Blue']);
        $this->makeEvent($survey, ['user_name' => 'Bob', 'user_email' => 'bob@example.com'], ['Red']);

        // data of other surveys / deleted participations must not leak in
        $other = $this->makeSurvey(['title' => 'Other']);
        $this->makeEvent($other, ['user_name' => 'Zed'], ['Green']);
        $gone = $this->makeEvent($survey, ['user_name' => 'Gone', 'user_email' => 'gone@example.com'], ['Green']);
        $gone->delete();

        $export = $this->exportRows((new ResultsExporter($survey))->build());
        $this->assertStringEndsWith('.xlsx', $export['filename']);
        $this->assertStringStartsWith('grosse-farbe-' . date('Y-m-d'), $export['filename']);
        $this->assertStringContainsString('spreadsheetml.sheet', $export['mime']);

        $parts = $this->unzip($export['path']);
        $responses = $this->sheetCells($parts['xl/worksheets/sheet1.xml']);
        $summary = $this->sheetCells($parts['xl/worksheets/sheet2.xml']);

        $this->assertCount(3, $responses, 'header + 2 participants');
        $flat = json_encode($responses);
        $this->assertStringContainsString('Ann', $flat);
        $this->assertStringContainsString('Red; Blue', $flat);
        $this->assertStringContainsString("'=cmd", json_encode($responses, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('Zed', $flat);
        $this->assertStringNotContainsString('Gone', $flat);

        // Red=2 (first, sorted by count), Blue=1; deleted participant's Green is not counted
        $this->assertSame('Red', $summary[1][0]);
        $this->assertSame('2', $summary[1][1]);
        $this->assertSame('1', $summary[1][2], '2 of 2 participants');
        $this->assertSame('Blue', $summary[2][0]);
        $this->assertSame('0.5', $summary[2][2]);
        $this->assertStringNotContainsString('Green', json_encode($summary));
        $this->assertStringContainsString('2', end($summary)[1]);
    }

    public function testExporterOmitsPersonalColumnsForAnonymousSurvey(): void
    {
        $this->requireZip();
        $survey = $this->makeSurvey(['is_anonym' => true]);
        $this->makeEvent($survey, ['user_name' => 'Secret', 'user_email' => 'secret@example.com',
            'user_meta' => ['user_phone' => '123456']], ['Red']);

        $export = $this->exportRows((new ResultsExporter($survey))->build());
        $xml = $this->unzip($export['path'])['xl/worksheets/sheet1.xml'];

        $this->assertStringNotContainsString('Secret', $xml);
        $this->assertStringNotContainsString('secret@example.com', $xml);
        $this->assertStringNotContainsString('123456', $xml);
        $this->assertStringContainsString('Red', $xml);
        $this->assertSame(4, count($this->sheetCells($xml)[0]), 'id, date, choices, duration');
    }

    public function testExporterHandlesEmptySurveyAndManyParticipants(): void
    {
        $this->requireZip();
        $empty = $this->makeSurvey(['title' => '!!!']);
        $export = $this->exportRows((new ResultsExporter($empty))->build());
        $this->assertStringStartsWith('survey-', $export['filename']);
        $this->assertCount(1, $this->sheetCells($this->unzip($export['path'])['xl/worksheets/sheet1.xml']));

        // more than one chunk (500)
        $survey = $this->makeSurvey();
        for ($i = 0; $i < 520; $i++) {
            $this->makeEvent($survey, ['user_name' => "U$i"], [$i % 2 ? 'Red' : 'Blue']);
        }
        $export = $this->exportRows((new ResultsExporter($survey))->build());
        $parts = $this->unzip($export['path']);
        $this->assertCount(521, $this->sheetCells($parts['xl/worksheets/sheet1.xml']));
        $summary = $this->sheetCells($parts['xl/worksheets/sheet2.xml']);
        $this->assertSame('260', $summary[1][1]);
    }

    public function testFilenameSlug(): void
    {
        $survey = $this->makeSurvey(['title' => str_repeat('Long title ', 20)]);
        $name = (new ResultsExporter($survey))->filename('csv');
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+-\d{4}-\d{2}-\d{2}\.csv$/', $name);
        $this->assertLessThanOrEqual(60 + 15, strlen($name));
    }
}
