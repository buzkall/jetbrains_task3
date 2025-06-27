<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class SurveyStructureImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected $data;
    
    public function __construct()
    {
        $this->data = collect();
    }
    
    public function collection(Collection $rows)
    {
        $this->data = $rows;
    }
    
    public function getData(): Collection
    {
        return $this->data;
    }
    
    public function chunkSize(): int
    {
        return 100; // Process 100 rows at a time
    }
}