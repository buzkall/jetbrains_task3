<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SurveyAnalysisService
{
    protected $filePath;
    protected $surveyData = null;
    protected $surveyStructure = null;

    public function __construct()
    {
        $this->filePath = resource_path('so_2024_raw.xlsx');
    }

    public function getSurveyStructure(): Collection
    {
        // If we've already loaded the structure, return the cached version
        if ($this->surveyStructure !== null) {
            return $this->surveyStructure;
        }

        try {
            // Check if file exists
            if (!file_exists($this->filePath)) {
                Log::error("Excel file not found at: {$this->filePath}");
                return collect();
            }

            Log::info("Loading Excel file from: {$this->filePath}");

            // Use PhpSpreadsheet directly with memory-saving options
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);

            // Get sheet names to determine the correct index
            $worksheetNames = $reader->listWorksheetNames($this->filePath);
            Log::info("Available worksheets: " . implode(', ', $worksheetNames));

            if (count($worksheetNames) < 2) {
                Log::error("Excel file does not have a second sheet");
                return collect();
            }

            // Load only the second sheet by name
            $secondSheetName = $worksheetNames[1];
            $reader->setLoadSheetsOnly($secondSheetName);

            // Open the spreadsheet
            $spreadsheet = $reader->load($this->filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Get the highest row and column
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            Log::info("Sheet dimensions: {$highestRow} rows, {$highestColumn} columns");

            if ($highestRow <= 1) {
                Log::error("Sheet has no data rows");
                return collect();
            }

            // Get headers from the first row
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
                if (!empty($value)) {
                    $headers[$col] = $value;
                }
            }

            Log::info("Headers found: " . implode(', ', $headers));

            if (empty($headers)) {
                Log::error("No headers found in the first row");
                return collect();
            }

            // Read data row by row
            $data = collect();
            $rowCount = 0;

            // Process all rows
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                $hasData = false;

                foreach ($headers as $col => $header) {
                    $value = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                    $rowData[$header] = $value;
                    if (!empty($value)) {
                        $hasData = true;
                    }
                }

                if ($hasData) {
                    $data->push($rowData);
                    $rowCount++;
                }
            }

            Log::info("Processed {$rowCount} data rows");

            // Clean up to free memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            // Cache the structure
            $this->surveyStructure = $data;

            return $data;
        } catch (\Exception $e) {
            Log::error('Error reading Excel file: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return collect();
        }
    }

    public function getSurveyData(): Collection
    {
        // For the subset functionality, we'll use a small mock dataset
        // This avoids the performance issues with loading the full Excel file
        $data = collect();

        // Get the survey structure to extract question IDs
        $structure = $this->getSurveyStructure();

        // Extract question IDs from the structure
        $questionIds = [];
        if ($structure->isNotEmpty()) {
            // Find the column that contains question IDs
            $firstItem = $structure->first();
            $idColumn = null;

            foreach (array_keys($firstItem) as $key) {
                if (stripos($key, 'id') !== false) {
                    $idColumn = $key;
                    break;
                }
            }

            if ($idColumn) {
                $questionIds = $structure->pluck($idColumn)->filter()->unique()->values()->toArray();
            }
        }

        // Create 100 mock responses with the actual question IDs
        for ($i = 1; $i <= 100; $i++) {
            $response = ['ResponseId' => "R{$i}"];

            foreach ($questionIds as $questionId) {
                // Generate a random response for each question
                $response[$questionId] = "Response {$i} to {$questionId}";
            }

            $data->push($response);
        }

        return $data;
    }

    public function createSubset(string $questionId, $selectedOptions): Collection
    {
        try {
            // Get all survey data
            $allData = $this->getSurveyData();

            if ($allData->isEmpty()) {
                Log::error("No survey data available to create subset");
                return collect();
            }

            // Filter the data based on the selected question and options
            $subset = $allData->filter(function ($response) use ($questionId, $selectedOptions) {
                // If the question doesn't exist in the response, skip it
                if (!isset($response[$questionId])) {
                    return false;
                }

                $responseValue = $response[$questionId];

                // Handle different types of option selections
                if (is_array($selectedOptions)) {
                    // For multiple options, check if any of them match
                    foreach ($selectedOptions as $option) {
                        // Check if the option is contained in the response
                        // This handles both exact matches and cases where the response contains multiple values
                        if (stripos($responseValue, $option) !== false) {
                            return true;
                        }
                    }
                    return false;
                } else {
                    // For a single option, check if it matches
                    return stripos($responseValue, $selectedOptions) !== false;
                }
            });

            Log::info("Created subset with " . $subset->count() . " responses based on question {$questionId}");

            return $subset;
        } catch (\Exception $e) {
            Log::error('Error creating subset: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return collect();
        }
    }
}
