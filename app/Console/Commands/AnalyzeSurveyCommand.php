<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

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
                    'exit' => 'Exit',
                ]
            );

            if ($option === 'structure') {
                $this->displaySurveyStructure();
            } elseif ($option === 'search') {
                $this->searchSurveyStructure();
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
