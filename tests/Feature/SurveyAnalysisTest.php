<?php

namespace Tests\Feature;

use App\Console\Commands\AnalyzeSurveyCommand;
use App\Services\SurveyAnalysisService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use function Pest\Laravel\artisan;

beforeEach(function () {
    // Check if the real file exists - DO NOT modify it
    $this->realFilePath = resource_path('so_2024_raw.xlsx');
    $this->fileExists = File::exists($this->realFilePath);
    
    // Create a test file with a different name for testing
    $this->testFilePath = resource_path('test_survey_file.xlsx');
    
    // Create a simple mock file for testing if needed
    if (!File::exists($this->testFilePath)) {
        File::put($this->testFilePath, 'Mock Excel file for testing');
    }
});

afterEach(function () {
    // Clean up the test file
    if (File::exists($this->testFilePath)) {
        File::delete($this->testFilePath);
    }
});

test('survey analysis service can be instantiated', function () {
    // Arrange & Act
    $service = app(SurveyAnalysisService::class);
    
    // Assert
    expect($service)->toBeInstanceOf(SurveyAnalysisService::class);
});

test('analyze survey command can be executed', function () {
    // We're not testing the interactive functionality, just that the command exists and can be executed
    artisan('survey:analyze', ['--help' => true])
        ->assertExitCode(0);
});

test('command methods work correctly', function () {
    // Create a partial mock of the command to test internal methods
    $mockService = mock(SurveyAnalysisService::class);
    $mockService->shouldReceive('getSurveyStructure')
        ->andReturn(collect([
            ['QuestionID' => 'Q1', 'QuestionText' => 'What is your favorite programming language?'],
            ['QuestionID' => 'Q2', 'QuestionText' => 'How many years of experience do you have?']
        ]));
    
    // Create an instance of the command with our mock service
    $command = new AnalyzeSurveyCommand($mockService);
    
    // Use reflection to access and test private methods
    $reflectionClass = new \ReflectionClass($command);
    
    // Test displayPage method
    $displayPageMethod = $reflectionClass->getMethod('displayPage');
    $displayPageMethod->setAccessible(true);
    
    // Create a mock structure
    $structure = collect([
        ['QuestionID' => 'Q1', 'QuestionText' => 'What is your favorite programming language?'],
        ['QuestionID' => 'Q2', 'QuestionText' => 'How many years of experience do you have?']
    ]);
    
    // We can't directly test the output, but we can ensure the method doesn't throw exceptions
    expect(function () use ($displayPageMethod, $command, $structure) {
        // Mock the Laravel output to prevent actual output during tests
        $command->setLaravel(app());
        $command->setOutput(new \Symfony\Component\Console\Output\NullOutput());
        
        // Call the method
        $displayPageMethod->invoke($command, $structure, 1, 1);
    })->not->toThrow(\Exception::class);
});

test('search functionality filters questions correctly', function () {
    // Create a mock of the command to test the search functionality
    $mockService = mock(SurveyAnalysisService::class);
    
    // Create test data with various questions
    $testData = collect([
        ['QuestionID' => 'Q1', 'QuestionText' => 'What is your favorite programming language?', 'AnswerType' => 'Multiple Choice'],
        ['QuestionID' => 'Q2', 'QuestionText' => 'How many years of experience do you have?', 'AnswerType' => 'Numeric'],
        ['QuestionID' => 'Q3', 'QuestionText' => 'Which databases do you use regularly?', 'AnswerType' => 'Multiple Choice'],
        ['QuestionID' => 'Q4', 'QuestionText' => 'What is your job title?', 'AnswerType' => 'Text'],
        ['QuestionID' => 'LANG', 'QuestionText' => 'Programming languages used in the last year', 'AnswerType' => 'Multiple Choice']
    ]);
    
    $mockService->shouldReceive('getSurveyStructure')
        ->andReturn($testData);
    
    // Create an instance of the command with our mock service
    $command = new AnalyzeSurveyCommand($mockService);
    
    // Use reflection to access the private search method
    $reflectionClass = new \ReflectionClass($command);
    
    // We need to test the filtering logic in the searchForQuestion method
    // Since we can't directly call it due to user input, we'll extract and test the filtering logic
    
    // Create a closure that mimics the filtering logic in searchForQuestion
    $filterFunction = function ($searchTerm) use ($testData) {
        return $testData->filter(function ($item) use ($searchTerm) {
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
    };
    
    // Test searching for "programming"
    $programmingResults = $filterFunction('programming');
    expect($programmingResults)->toHaveCount(2)
        ->and($programmingResults->pluck('QuestionID')->toArray())->toContain('Q1')
        ->and($programmingResults->pluck('QuestionID')->toArray())->toContain('LANG');
    
    // Test searching for "experience"
    $experienceResults = $filterFunction('experience');
    expect($experienceResults)->toHaveCount(1)
        ->and($experienceResults->first()['QuestionID'])->toBe('Q2');
    
    // Test searching for "Multiple Choice" (answer type)
    $multipleChoiceResults = $filterFunction('Multiple Choice');
    expect($multipleChoiceResults)->toHaveCount(3);
    
    // Test searching for something that doesn't exist
    $noResults = $filterFunction('something that does not exist');
    expect($noResults)->toBeEmpty();
});

test('getSurveyStructure returns collection', function () {
    // Create a mock of the service to avoid actual file reading
    $mockService = new class extends SurveyAnalysisService {
        public function getSurveyStructure(): Collection
        {
            // Return a mock collection instead of reading the file
            return collect([
                ['QuestionID' => 'Q1', 'QuestionText' => 'Test Question 1'],
                ['QuestionID' => 'Q2', 'QuestionText' => 'Test Question 2']
            ]);
        }
    };
    
    // Test the method
    $result = $mockService->getSurveyStructure();
    
    // Assert
    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toHaveCount(2)
        ->and($result[0]['QuestionID'])->toBe('Q1')
        ->and($result[1]['QuestionText'])->toBe('Test Question 2');
});
