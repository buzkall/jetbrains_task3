<?php

namespace App\Console\Commands\Traits;

use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\info;

trait PaginatesResults
{
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
        
        info("Page {$currentPage} of {$totalPages} (showing {$this->perPage} of {$structure->count()} items)");
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