<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class SurveyDataImport implements ToCollection, WithHeadingRow, WithLimit, WithChunkReading
{
    protected $data;
    protected $maxRows;
    
    public function __construct(int $maxRows = 250)
    {
        $this->data = collect();
        $this->maxRows = $maxRows;
    }
    
    public function collection(Collection $rows)
    {
        // Process only up to maxRows
        $this->data = $rows->take($this->maxRows);
    }
    
    public function getData(): Collection
    {
        return $this->data;
    }
    
    public function limit(): int
    {
        return $this->maxRows;
    }
    
    public function chunkSize(): int
    {
        return 100; // Process 100 rows at a time
    }
}