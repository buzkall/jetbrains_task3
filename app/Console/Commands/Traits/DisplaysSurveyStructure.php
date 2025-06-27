<?php

namespace App\Console\Commands\Traits;

use Illuminate\Support\Facades\Log;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;

trait DisplaysSurveyStructure
{
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
}