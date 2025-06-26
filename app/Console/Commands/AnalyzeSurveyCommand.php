<?php

namespace App\Console\Commands;

use App\Services\SurveyAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;

class AnalyzeSurveyCommand extends Command
{
    protected $signature = 'survey:analyze {--memory-limit=1024M} {--debug} {--page=1}';
    protected $description = 'Analyze Stack Overflow 2024 survey data';
    protected $surveyService;
    protected $perPage = 20;

    public function __construct(SurveyAnalysisService $surveyService)
    {
        parent::__construct();
        $this->surveyService = $surveyService;
    }

    public function handle()
    {
        // Set memory limit if provided
        if ($this->option('memory-limit')) {
            ini_set('memory_limit', $this->option('memory-limit'));
        }

        // Enable debug mode if requested
        if ($this->option('debug')) {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }

        $this->showMainMenu();

        return Command::SUCCESS;
    }

    private function showMainMenu()
    {
        info('Stack Overflow 2024 Survey Analysis Tool');
        note('Analyze data from the Stack Overflow 2024 survey');

        $choice = $this->menu();

        switch ($choice) {
            case 'Display survey structure':
                $this->displaySurveyStructure();
                // After returning from displaySurveyStructure, show the main menu again
                $this->showMainMenu();
                break;
            case 'Search for question':
                $this->searchForQuestion();
                // After returning from searchForQuestion, show the main menu again
                $this->showMainMenu();
                break;
            case 'Exit':
                info('Goodbye!');
                break;
            default:
                error('Invalid option selected');
                $this->showMainMenu();
                break;
        }
    }

    private function menu(): string
    {
        return select(
            label: 'What would you like to do?',
            options: [
                'Display survey structure' => 'Display survey structure (list of questions)',
                'Search for question' => 'Search for specific question or option',
                'Exit' => 'Exit the application'
            ],
            default: 'Display survey structure'
        );
    }

    private function displaySurveyStructure(): void
    {
        info('Survey Structure (Questions)');

        // Check if file exists
        $filePath = resource_path('so_2024_raw.xlsx');
        if (!file_exists($filePath)) {
            error("Excel file not found at: {$filePath}");
            note("Please make sure to place the so_2024_raw.xlsx file in the resources directory.");
            return;
        }

        note("Loading Excel file from: {$filePath}");

        try {
            $structure = spin(
                fn () => $this->surveyService->getSurveyStructure(),
                'Loading survey structure...'
            );

            if ($structure->isEmpty()) {
                error('Could not load survey structure from the Excel file.');
                note('Please check the Laravel log for detailed error information.');

                if ($this->option('debug')) {
                    $logPath = storage_path('logs/laravel.log');
                    if (file_exists($logPath)) {
                        $logContent = file_get_contents($logPath);
                        $this->line("\nRecent log entries:");
                        $this->line(substr($logContent, max(0, strlen($logContent) - 2000)));
                    }
                }
                return;
            }

            // Get headers from the first item
            if ($structure->first()) {
                $this->paginateResults($structure);
            } else {
                error('The survey structure sheet exists but contains no data.');
            }
        } catch (\Exception $e) {
            error('Error processing survey structure: ' . $e->getMessage());
            Log::error('Command exception: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            note('Try running the command with increased memory limit and debug option:');
            $this->line('php artisan survey:analyze --memory-limit=2048M --debug');
        }
    }

    private function searchForQuestion(): void
    {
        info('Search for Question or Option');

        // Check if file exists
        $filePath = resource_path('so_2024_raw.xlsx');
        if (!file_exists($filePath)) {
            error("Excel file not found at: {$filePath}");
            note("Please make sure to place the so_2024_raw.xlsx file in the resources directory.");
            return;
        }

        // Get search term from user
        $searchTerm = text(
            label: 'Enter search term',
            placeholder: 'e.g. programming language, experience, etc.',
            required: true
        );

        note("Searching for: {$searchTerm}");

        try {
            $structure = spin(
                fn () => $this->surveyService->getSurveyStructure(),
                'Loading survey structure...'
            );

            if ($structure->isEmpty()) {
                error('Could not load survey structure from the Excel file.');
                return;
            }

            // Search for the term in QuestionText and other relevant fields
            $results = $structure->filter(function ($item) use ($searchTerm) {
                // Search in QuestionText
                if (isset($item['QuestionText']) && stripos($item['QuestionText'], $searchTerm) !== false) {
                    return true;
                }

                // Search in QuestionID
                if (isset($item['QuestionID']) && stripos($item['QuestionID'], $searchTerm) !== false) {
                    return true;
                }

                // Search in AnswerType
                if (isset($item['AnswerType']) && stripos($item['AnswerType'], $searchTerm) !== false) {
                    return true;
                }

                // Search in any other field
                foreach ($item as $key => $value) {
                    if (is_string($value) && stripos($value, $searchTerm) !== false) {
                        return true;
                    }
                }

                return false;
            });

            if ($results->isEmpty()) {
                info("No results found for '{$searchTerm}'.");
                return;
            }

            info("Found " . $results->count() . " results for '{$searchTerm}':");

            // Display the results
            $this->paginateResults($results);

        } catch (\Exception $e) {
            error('Error searching questions: ' . $e->getMessage());
            Log::error('Search exception: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    private function paginateResults($structure)
    {
        $totalItems = $structure->count();
        $totalPages = ceil($totalItems / $this->perPage);
        $currentPage = (int) $this->option('page');

        // Ensure valid page number
        if ($currentPage < 1) {
            $currentPage = 1;
        } elseif ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $this->displayPage($structure, $currentPage, $totalPages);

        // Continue pagination until user exits
        $this->continuePagination($structure, $currentPage, $totalPages);
    }

    private function displayPage($structure, $currentPage, $totalPages)
    {
        $headers = array_keys($structure->first());

        // Get the current page of data
        $offset = ($currentPage - 1) * $this->perPage;
        $currentPageData = $structure->slice($offset, $this->perPage);

        // Create rows for the table
        $rows = $currentPageData->map(function ($row) {
            return $row;
        })->toArray();

        // Display the data in a table
        table(
            headers: $headers,
            rows: $rows
        );

        info("Page {$currentPage} of {$totalPages} (showing {$this->perPage} of {$structure->count()} questions)");
    }

    private function continuePagination($structure, $currentPage, $totalPages)
    {
        if ($totalPages <= 1) {
            return;
        }

        $options = [];

        if ($currentPage > 1) {
            $options['previous'] = 'Previous page';
        }

        if ($currentPage < $totalPages) {
            $options['next'] = 'Next page';
        }

        $options['goto'] = 'Go to specific page';
        $options['exit'] = 'Return to main menu';

        $choice = select(
            label: 'Navigation',
            options: $options
        );

        switch ($choice) {
            case 'previous':
                $this->displayPage($structure, $currentPage - 1, $totalPages);
                $this->continuePagination($structure, $currentPage - 1, $totalPages);
                break;

            case 'next':
                $this->displayPage($structure, $currentPage + 1, $totalPages);
                $this->continuePagination($structure, $currentPage + 1, $totalPages);
                break;

            case 'goto':
                $pageNumber = (int) text(
                    label: "Enter page number (1-{$totalPages})",
                    default: (string) $currentPage,
                    validate: fn ($value) =>
                        is_numeric($value) && $value >= 1 && $value <= $totalPages
                            ? null
                            : "Please enter a valid page number between 1 and {$totalPages}"
                );

                if ($pageNumber !== $currentPage) {
                    $this->displayPage($structure, $pageNumber, $totalPages);
                    $this->continuePagination($structure, $pageNumber, $totalPages);
                } else {
                    $this->continuePagination($structure, $currentPage, $totalPages);
                }
                break;

            case 'exit':
                // Just return to exit pagination and go back to main menu
                return;
        }
    }
}
