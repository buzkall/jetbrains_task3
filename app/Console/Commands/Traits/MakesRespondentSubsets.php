<?php

namespace App\Console\Commands\Traits;

use Illuminate\Support\Facades\Log;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\table;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\confirm;

trait MakesRespondentSubsets
{
    private function makeRespondentsSubset(): void
    {
        info('Create Respondents Subset');
        
        // Check if file exists
        $filePath = resource_path('so_2024_raw.xlsx');
        if (!file_exists($filePath)) {
            error("Excel file not found at: {$filePath}");
            note("Please make sure to place the so_2024_raw.xlsx file in the resources directory.");
            return;
        }
        
        try {
            // First, load the survey structure to get the list of questions
            $structure = spin(
                fn () => $this->surveyService->getSurveyStructure(),
                'Loading survey structure...'
            );

            if ($structure->isEmpty()) {
                error('Could not load survey structure from the Excel file.');
                return;
            }
            
            // Find the column names for question ID and text
            $firstItem = $structure->first();
            $columns = array_keys($firstItem);
            
            // Try to identify the ID and text columns
            $idColumn = null;
            $textColumn = null;
            
            foreach ($columns as $column) {
                if (stripos($column, 'id') !== false) {
                    $idColumn = $column;
                }
                if (stripos($column, 'text') !== false) {
                    $textColumn = $column;
                }
            }
            
            if (!$idColumn || !$textColumn) {
                // If we couldn't find them automatically, use the first two columns
                $idColumn = $columns[0];
                $textColumn = $columns[1];
            }
            
            // Create a list of questions for selection
            $questions = [];
            foreach ($structure as $item) {
                $questionId = $item[$idColumn] ?? null;
                $questionText = $item[$textColumn] ?? null;
                
                if ($questionId && $questionText) {
                    // Truncate long question text for display
                    $displayText = substr($questionText, 0, 80) . (strlen($questionText) > 80 ? '...' : '');
                    $questions[$questionId] = "{$questionId}: {$displayText}";
                }
            }
            
            if (empty($questions)) {
                // If we couldn't extract questions from the structure, use mock questions
                $questions = [
                    'Q1' => 'Q1: What programming languages do you use regularly?',
                    'Q2' => 'Q2: How many years of experience do you have?',
                    'Q3' => 'Q3: What type of development do you do?',
                    'Q4' => 'Q4: Do you work remotely?',
                    'Q5' => 'Q5: What operating system do you use primarily?'
                ];
            }
            
            // Let the user select a question
            $selectedQuestionId = select(
                label: 'Select a question to filter respondents',
                options: $questions,
                scroll: 10
            );
            
            // Find the selected question details or use a mock
            $selectedQuestion = $structure->first(function ($item) use ($selectedQuestionId, $idColumn) {
                return isset($item[$idColumn]) && $item[$idColumn] === $selectedQuestionId;
            });
            
            $questionText = $selectedQuestion[$textColumn] ?? $questions[$selectedQuestionId] ?? 'Question details not available';
            
            info("Selected Question: {$selectedQuestionId}");
            note($questionText);
            
            // Load survey data to determine available options
            note("Loading survey data to determine available options...");
            
            $surveyData = spin(
                fn () => $this->surveyService->getSurveyData(),
                'Loading survey data...'
            );
            
            if ($surveyData->isEmpty()) {
                error('Could not load survey data to determine options.');
                return;
            }
            
            // Extract unique values for the selected question
            $uniqueOptions = $surveyData->pluck($selectedQuestionId)
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            
            // Format options for display
            $optionsForDisplay = [];
            foreach ($uniqueOptions as $option) {
                if (is_string($option)) {
                    $displayOption = substr($option, 0, 80) . (strlen($option) > 80 ? '...' : '');
                    $optionsForDisplay[$option] = $displayOption;
                }
            }
            
            if (empty($optionsForDisplay)) {
                error("No options found for question {$selectedQuestionId} in the survey data.");
                return;
            }
            
            // Let the user select one or more options
            $selectedOptions = multiselect(
                label: 'Select one or more options (respondents who selected ANY of these will be included)',
                options: $optionsForDisplay,
                required: true,
                scroll: 10
            );
            
            if (empty($selectedOptions)) {
                error("No options selected. Subset creation cancelled.");
                return;
            }
            
            // Create the subset
            note("Creating subset based on selected criteria...");
            
            $subset = spin(
                fn () => $this->surveyService->createSubset($selectedQuestionId, $selectedOptions),
                'Creating respondents subset...'
            );
            
            if ($subset->isEmpty()) {
                error("No respondents match the selected criteria.");
                return;
            }
            
            // Store the subset for later use
            $this->currentSubset = $subset;
            
            info("Subset created successfully with " . $subset->count() . " respondents.");
            
            // Show a sample of the subset
            $sampleSize = min(5, $subset->count());
            $sampleData = $subset->take($sampleSize)->toArray();
            
            note("Sample of the subset (first {$sampleSize} respondents):");
            
            // Get a limited set of columns to display
            $displayColumns = array_slice(array_keys($sampleData[0]), 0, 5);
            
            // Prepare rows for display
            $rows = [];
            foreach ($sampleData as $respondent) {
                $row = [];
                foreach ($displayColumns as $column) {
                    $row[$column] = substr($respondent[$column] ?? 'N/A', 0, 50);
                }
                $rows[] = $row;
            }
            
            // Display the sample
            table(
                headers: $displayColumns,
                rows: $rows
            );
            
            // Ask if user wants to save the subset
            if (confirm('Would you like to save this subset for further analysis?')) {
                $subsetName = text(
                    label: 'Enter a name for this subset',
                    placeholder: 'e.g. python_developers_2024',
                    required: true
                );
                
                // Here you would implement saving the subset
                // For now, we'll just acknowledge it
                info("Subset '{$subsetName}' is now active for further analysis.");
                note("In a full implementation, this would be saved to a file or database.");
            }
            
        } catch (\Exception $e) {
            error('Error creating respondents subset: ' . $e->getMessage());
            Log::error('Subset creation exception: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            if ($this->option('debug')) {
                $this->line("Exception details: " . $e->getMessage());
                $this->line("Stack trace: " . $e->getTraceAsString());
            }
        }
    }
}