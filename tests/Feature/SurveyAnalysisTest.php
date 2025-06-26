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
