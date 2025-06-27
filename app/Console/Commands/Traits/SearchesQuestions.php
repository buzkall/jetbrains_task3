<?php

namespace App\Console\Commands\Traits;

use Illuminate\Support\Facades\Log;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;

trait SearchesQuestions
{
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
}
