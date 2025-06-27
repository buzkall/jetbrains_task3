<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SurveyDataImport;

class TestExcelImportCommand extends Command
{
    protected $signature = 'survey:test-import {--limit=10}';
    protected $description = 'Test Excel import functionality';

    public function handle()
    {
        $this->info('Testing Excel import...');
        
        $filePath = resource_path('so_2024_raw.xlsx');
        
        if (!file_exists($filePath)) {
            $this->error("Excel file not found at: {$filePath}");
            return Command::FAILURE;
        }
        
        $this->info("Loading Excel file from: {$filePath}");
        
        try {
            $limit = (int) $this->option('limit');
            $import = new SurveyDataImport($limit);
            
            $this->info("Importing first {$limit} rows...");
            
            Excel::import($import, $filePath, null, \Maatwebsite\Excel\Excel::XLSX);
            
            $data = $import->getData();
            
            $this->info("Successfully imported " . $data->count() . " rows");
            
            if ($data->isNotEmpty()) {
                $this->info("First row sample:");
                $this->table(
                    array_keys($data->first()->toArray()),
                    [$data->first()->toArray()]
                );
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error importing Excel file: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}