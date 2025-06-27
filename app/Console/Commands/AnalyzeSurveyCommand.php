<?php

namespace App\Console\Commands;

use App\Services\SurveyAnalysisService;
use App\Console\Commands\Traits\DisplaysSurveyStructure;
use App\Console\Commands\Traits\SearchesQuestions;
use App\Console\Commands\Traits\MakesRespondentSubsets;
use App\Console\Commands\Traits\PaginatesResults;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use function Laravel\Prompts\select;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;

class AnalyzeSurveyCommand extends Command
{
    use DisplaysSurveyStructure,
        SearchesQuestions,
        MakesRespondentSubsets,
        PaginatesResults;

    protected $signature = 'survey:analyze {--memory-limit=1024M} {--debug} {--page=1}';
    protected $description = 'Analyze Stack Overflow 2024 survey data';
    protected $surveyService;
    protected $perPage = 20;
    protected $currentSubset = null;

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

        // Show subset info if one is active
        if ($this->currentSubset) {
            info("Current active subset: " . $this->currentSubset->count() . " respondents");
        }

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
            case 'Make respondents subset':
                $this->makeRespondentsSubset();
                // After returning from makeRespondentsSubset, show the main menu again
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
        $options = [
            'Display survey structure' => 'Display survey structure (list of questions)',
            'Search for question' => 'Search for specific question or option',
            'Make respondents subset' => 'Create a subset of respondents based on question+option',
            'Exit' => 'Exit the application'
        ];

        return select(
            label: 'What would you like to do?',
            options: $options,
            default: 'Display survey structure'
        );
    }
}
