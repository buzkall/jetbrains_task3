<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SurveyAnalysisService
{
    protected $filePath;

    public function __construct()
    {
        $this->filePath = resource_path('so_2024_raw.xlsx');
    }

    public function getSurveyStructure(): Collection
    {
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

            // Process all rows (removed the limit of 100)
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

            return $data;
        } catch (\Exception $e) {
            Log::error('Error reading Excel file: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return collect();
        }
    }
}
