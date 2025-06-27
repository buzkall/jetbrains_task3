<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AnalyzeSurveyCommand extends Command
{

    protected $signature = 'app:analyze-survey-command';


    protected $description = 'Analyze Stack Overflow Survey Data';


    public function handle()
    {
        while (true) {
            $option = select(
                'Select an option',
                [
                    'structure' => 'Display the survey structure',
                    'search' => 'Search for specific question / option',
                    'subset' => 'Make respondents subsets (based on question+option)',
                    'exit' => 'Exit',
                ]
            );

            if ($option === 'structure') {
                $this->displaySurveyStructure();
            } elseif ($option === 'search') {
                $this->searchSurveyStructure();
            } elseif ($option === 'subset') {
                $this->makeRespondentSubset();
            } elseif ($option === 'exit') {
                $this->info('Exiting.');
                break;
            }
        }
    }


    protected function displaySurveyStructure()
    {
        $questions = $this->loadSchemaQuestions();
        if (empty($questions)) {
            $this->warn('No questions found in the schema tab.');
            return;
        }
        $this->info('Survey Structure (Questions):');
        $headers = ['column', 'question_text'];
        $rows = array_map(function($q) {
            return explode(' | ', $q, 2);
        }, $questions);
        table($headers, $rows);
    }

    protected function makeRespondentSubset()
    {
        // Prompt for question code (select)
        $questions = $this->loadSchemaQuestions();
        if (empty($questions)) {
            $this->warn('No questions found in the schema tab.');
            return;
        }
        $headers = ['column', 'question_text'];
        $rows = array_map(function($q) {
            return explode(' | ', $q, 2);
        }, $questions);
        $questionOptions = [];
        $codeToRaw = [];
        foreach ($rows as $row) {
            // $row[0] = code, $row[1] = label, $row[2] = raw data key (if present)
            $code = trim($row[0]);
            $label = isset($row[1]) ? $row[1] : '';
            $rawKey = isset($row[2]) && $row[2] !== '' ? trim($row[2]) : $code; // fallback to code if 3rd col missing
            if ($code !== '' && $rawKey !== '') {
                $questionOptions[$code] = ($label !== '' ? $label . ' | ' : '') . $code . ' | ' . $rawKey;
                $codeToRaw[$code] = $rawKey;
            }
        }
        $question = select('Select question code (column):', $questionOptions);
        if (!$question) {
            $this->warn('No question selected.');
            return;
        }
        $rawDataCol = $codeToRaw[$question] ?? $question;
        if (!$rawDataCol) {
            $this->warn('Could not map question code to raw data column.');
            return;
        }
        // Use chunk reading for large files
        $filePath = resource_path('so_2024_raw.xlsx');
        $this->info('Scanning file for unique values in selected column. This may take a moment...');
        $uniqueValues = [];
        $chunkSize = 1000;
        $questionCol = $rawDataCol;
        $rowCount = 0;
        $uniqueValuesRef = &$uniqueValues;
        $rowCountRef = &$rowCount;
        $cmd = $this;
        \Maatwebsite\Excel\Facades\Excel::import(new class($questionCol, $uniqueValuesRef, $rowCountRef, $cmd) implements \Maatwebsite\Excel\Concerns\OnEachRow, \Maatwebsite\Excel\Concerns\WithHeadingRow, \Maatwebsite\Excel\Concerns\WithChunkReading {
            private $col;
            private $uniqueValues;
            private $rowCount;
            private $cmd;
            public function __construct($col, & $uniqueValues, & $rowCount, $cmd) { $this->col = $col; $this->uniqueValues = &$uniqueValues; $this->rowCount = &$rowCount; $this->cmd = $cmd; }
            public function onRow(\Maatwebsite\Excel\Row $row) {
                $arr = $row->toArray();
                $this->rowCount++;
                if ($this->rowCount === 1) {
                    $this->cmd->info('First raw data row keys: ' . implode(', ', array_keys($arr)));
                }
                $colKey = $this->col;
                if (!isset($arr[$colKey]) && isset($arr[strtolower($colKey)])) {
                    $colKey = strtolower($colKey);
                }
                if ($this->rowCount <= 5) {
                    $val = isset($arr[$colKey]) ? $arr[$colKey] : '[NOT SET]';
                    $this->cmd->info('Row ' . $this->rowCount . ' value for "' . $colKey . '": ' . var_export($val, true));
                }
                if ($this->rowCount % 1000 === 0) {
                    $this->cmd->info('Processed ' . $this->rowCount . ' rows, unique values so far: ' . count($this->uniqueValues));
                }
                if (!isset($arr[$colKey])) return;
                foreach (explode(';', (string)$arr[$colKey]) as $val) {
                    $val = trim($val);
                    if ($val !== '' && !in_array($val, $this->uniqueValues, true)) {
                        $this->uniqueValues[] = $val;
                    }
                }
            }
            public function chunkSize(): int { return 1000; }
        }, $filePath);
        $this->info('Finished scanning for unique values. Total rows processed: ' . $rowCount . ', unique values found: ' . count($uniqueValues));
        sort($uniqueValues);
        // Prompt for option value
        if (count($uniqueValues) > 50) {
            $option = text('Enter option/value to filter by (will match as substring):');
        } else {
            $option = select('Select option/value to filter by (will match as substring):', array_combine($uniqueValues, $uniqueValues));
        }
        if (!$option) {
            $this->warn('No option/value selected.');
            return;
        }
        // Now scan again to count and preview matching respondents
        $this->info('Scanning file for matching respondents...');
        $matchCount = 0;
        $previewRows = [];
        $headers = [];
        $matchCountRef = &$matchCount;
        $previewRowsRef = &$previewRows;
        $headersRef = &$headers;
        \Maatwebsite\Excel\Facades\Excel::import(new class($questionCol, $option, $matchCountRef, $previewRowsRef, $headersRef) implements \Maatwebsite\Excel\Concerns\OnEachRow, \Maatwebsite\Excel\Concerns\WithHeadingRow, \Maatwebsite\Excel\Concerns\WithChunkReading {
            private $col;
            private $option;
            private $matchCount;
            private $previewRows;
            private $headers;
            public function __construct($col, $option, & $matchCount, & $previewRows, & $headers) {
                $this->col = $col;
                $this->option = $option;
                $this->matchCount = &$matchCount;
                $this->previewRows = &$previewRows;
                $this->headers = &$headers;
            }
            public function onRow(\Maatwebsite\Excel\Row $row) {
                $arr = $row->toArray();
                if (empty($this->headers) && !empty($arr)) {
                    $this->headers = array_keys($arr);
                }
                if (isset($arr[$this->col]) && stripos((string)$arr[$this->col], $this->option) !== false) {
                    $this->matchCount++;
                    if (count($this->previewRows) < 10) {
                        $this->previewRows[] = array_values($arr);
                    }
                }
            }
            public function chunkSize(): int { return 1000; }
        }, $filePath);
        $this->info('Found ' . $matchCount . ' respondents matching: ' . $question . ' contains "' . $option . '"');
        if (!empty($previewRows)) {
            $this->info('Showing first 10 respondents:');
            table($headers, $previewRows);
        }
    }


    protected function loadSchemaQuestions(): array
    {
        // Use maatwebsite/excel to read only the 'schema' sheet by name
        $questions = [];
        $this->info('Starting Excel import (sheet: schema)...');

        // Local import class for the schema sheet
        $cmd = $this;
        $schemaImport = new class($questions, $cmd) implements \Maatwebsite\Excel\Concerns\ToCollection {
            private $questions;
            private $cmd;
            public function __construct(& $questions, $cmd) { $this->questions = &$questions; $this->cmd = $cmd; }
            public function collection($collection) {
                $this->cmd->info('collection() called for schema, rows: ' . count($collection));
                foreach ($collection as $i => $row) {
                    if ($i === 0) continue; // skip header
                    $this->questions[] = implode(' | ', array_filter($row->toArray()));
                }
            }
        };
        $import = new class($schemaImport) implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
            private $schemaImport;
            public function __construct($schemaImport) { $this->schemaImport = $schemaImport; }
            public function sheets(): array {
                return [
                    'schema' => $this->schemaImport
                ];
            }
        };
        $filePath = resource_path('so_2024_raw.xlsx');
        Excel::import($import, $filePath);
        $this->info('Import finished for schema sheet. Questions collected: ' . count($questions));
        return $questions;
    }

    protected function searchSurveyStructure()
    {
        $questions = $this->loadSchemaQuestions();
        if (empty($questions)) {
            $this->warn('No questions found in the schema tab.');
            return;
        }
        $search = text('Enter search term (question text or code):');
        if (!$search) {
            $this->warn('No search term entered.');
            return;
        }
        $headers = ['column', 'question_text'];
        $rows = array_filter(array_map(function($q) use ($search) {
            $cols = explode(' | ', $q, 2);
            return (stripos($cols[0], $search) !== false || (isset($cols[1]) && stripos($cols[1], $search) !== false)) ? $cols : null;
        }, $questions));
        if (empty($rows)) {
            $this->warn('No matches found.');
        } else {
            table($headers, array_values($rows));
        }
    }

}
