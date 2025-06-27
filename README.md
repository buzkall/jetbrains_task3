# Stack Overflow 2024 Survey Analysis Tool

A Laravel-based tool for analyzing data from the Stack Overflow 2024 survey.

## Requirements

- PHP 8.1 or higher
- Composer
- Laravel 12
- Sufficient memory (at least 2GB recommended)

## Installation

1. Clone the repository:

```bash
git clone <repository-url>
cd task3_analyze_stack_overflow_survey/task3
```

2. Install dependencies:

```bash
composer install
```

3. Copy the `.env.example` file to `.env`:

```bash
cp .env.example .env
```

4. Generate an application key:

```bash
php artisan key:generate
```

5. Place the Stack Overflow 2024 survey Excel file in the resources directory:

```bash
# Make sure the file is named 'so_2024_raw.xlsx'
cp /path/to/your/survey/file.xlsx resources/so_2024_raw.xlsx
```

## Usage

Run the survey analysis command:

```bash
php artisan survey:analyze --memory-limit=2048M
```

### Command Options

- `--memory-limit=2048M`: Set the PHP memory limit for processing large Excel files
- `--debug`: Enable debug mode for more detailed error information
- `--page=1`: Start at a specific page of results

### Features

The tool provides an interactive menu with the following options:

1. **Display survey structure**: Shows the list of questions from the survey
   - Displays 20 questions per page
   - Provides navigation to move between pages
   - Allows jumping to a specific page

2. **Search for question**: Search for specific questions or options in the survey
   - Enter a search term to find matching questions
   - Searches across QuestionID, QuestionText, AnswerType, and other fields
   - Results are displayed with the same pagination system as the survey structure
   - Case-insensitive search that finds partial matches

3. **Make respondents subset**: Create a filtered subset of survey respondents
   - Select a question to filter by
   - Choose one or more answer options
   - Creates a subset of respondents who selected any of the chosen options
   - Displays a sample of the resulting subset
   - Option to save the subset for further analysis
   - The active subset is displayed in the main menu

4. **Exit**: Exit the application

### Troubleshooting

If you encounter memory issues, try increasing the memory limit:

```bash
php artisan survey:analyze --memory-limit=4096M
```

If you're using Laravel Sail, run the command with:

```bash
./vendor/bin/sail artisan survey:analyze --memory-limit=2048M
```

## Testing

The project includes unit tests built with Pest. To run the tests:

```bash
./vendor/bin/pest
```

### Test Coverage

The tests cover:

1. Service instantiation and functionality
2. Command execution
3. Internal command methods
4. Data retrieval and processing
5. Search functionality for finding specific questions

### Writing Additional Tests

To add more tests, create new test files in the `tests/Feature` or `tests/Unit` directories. For example:

```php
// tests/Feature/YourNewTest.php
<?php

test('your new test', function () {
    // Your test code here
    expect(true)->toBeTrue();
});
```

### Important Testing Note

⚠️ **CAUTION**: When writing tests, never modify or overwrite the actual data files. The tests in this project use separate test files to avoid corrupting the real survey data.

## Dependencies

This tool uses the following packages:

- [Laravel Prompts](https://github.com/laravel/prompts) for interactive CLI
- [Maatwebsite/Excel](https://github.com/Maatwebsite/Laravel-Excel) for Excel file handling
- [Pest PHP](https://pestphp.com/) for testing

## License

[MIT License](LICENSE)
