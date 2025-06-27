<?php

namespace Tests\Feature;

use App\Services\SurveyAnalysisService;
use Illuminate\Support\Collection;
use function Pest\Laravel\artisan;

test('create subset filters respondents correctly', function () {
    // Create a mock of the service to avoid actual file reading
    $mockService = new class extends SurveyAnalysisService {
        public function getSurveyData(): Collection
        {
            // Return mock survey data
            return collect([
                [
                    'ResponseId' => '1',
                    'Q1' => 'Python',
                    'Q2' => '5-10 years'
                ],
                [
                    'ResponseId' => '2',
                    'Q1' => 'JavaScript',
                    'Q2' => '1-5 years'
                ],
                [
                    'ResponseId' => '3',
                    'Q1' => 'Python, JavaScript',
                    'Q2' => '10+ years'
                ],
                [
                    'ResponseId' => '4',
                    'Q1' => 'Java',
                    'Q2' => '1-5 years'
                ],
                [
                    'ResponseId' => '5',
                    'Q1' => 'C#, Python',
                    'Q2' => '5-10 years'
                ]
            ]);
        }
    };
    
    // Test filtering by a single option
    $pythonSubset = $mockService->createSubset('Q1', 'Python');
    expect($pythonSubset)->toHaveCount(3)
        ->and($pythonSubset->pluck('ResponseId')->toArray())->toContain('1')
        ->and($pythonSubset->pluck('ResponseId')->toArray())->toContain('3')
        ->and($pythonSubset->pluck('ResponseId')->toArray())->toContain('5');
    
    // Test filtering by multiple options
    $multipleSubset = $mockService->createSubset('Q1', ['JavaScript', 'Java']);
    expect($multipleSubset)->toHaveCount(3)
        ->and($multipleSubset->pluck('ResponseId')->toArray())->toContain('2')
        ->and($multipleSubset->pluck('ResponseId')->toArray())->toContain('3')
        ->and($multipleSubset->pluck('ResponseId')->toArray())->toContain('4');
    
    // Test filtering by experience level
    $experienceSubset = $mockService->createSubset('Q2', '5-10 years');
    expect($experienceSubset)->toHaveCount(2)
        ->and($experienceSubset->pluck('ResponseId')->toArray())->toContain('1')
        ->and($experienceSubset->pluck('ResponseId')->toArray())->toContain('5');
});

test('create subset handles empty results', function () {
    // Create a mock of the service to avoid actual file reading
    $mockService = new class extends SurveyAnalysisService {
        public function getSurveyData(): Collection
        {
            // Return mock survey data
            return collect([
                [
                    'ResponseId' => '1',
                    'Q1' => 'Python',
                    'Q2' => '5-10 years'
                ],
                [
                    'ResponseId' => '2',
                    'Q1' => 'JavaScript',
                    'Q2' => '1-5 years'
                ]
            ]);
        }
    };
    
    // Test filtering by a non-existent option
    $emptySubset = $mockService->createSubset('Q1', 'Ruby');
    expect($emptySubset)->toBeEmpty();
    
    // Test filtering by a non-existent question
    $nonExistentSubset = $mockService->createSubset('Q99', 'Any value');
    expect($nonExistentSubset)->toBeEmpty();
});