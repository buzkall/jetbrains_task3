# Stack Overflow Survey Analyzer

This project is a Laravel-based command-line tool for analyzing Stack Overflow survey data. It provides interactive commands to inspect the survey structure, search for questions/options, and create respondent subsets.

## Requirements
- PHP >= 8.2
- Composer
- [Optional] Node.js & npm (for frontend assets, not required for CLI commands)

## Installation

1. **Clone the repository**
   ```bash
   git clone <your-repo-url>
   cd folder_name
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Copy the example environment file and configure as needed**
   ```bash
   cp .env.example .env
   # Edit .env if needed
   ```

4. **Generate application key**
   ```bash
   php artisan key:generate
   ```

5. **(Optional) Install Node dependencies (for frontend)**
   ```bash
   npm install
   ```

## Running the Analyzer Command

To run the interactive Stack Overflow survey analyzer, use:

```bash
php artisan app:analyze-survey-command
```

You will be presented with a menu to:
- Display the survey structure
- Search for specific questions/options
- Make respondent subsets
- Exit

Follow the on-screen prompts to interact with the survey data.

## Notes
- Ensure your survey data files are placed in the appropriate directory as expected by the command.
- For Excel/CSV functionality, the project uses `maatwebsite/excel` and `phpoffice/phpspreadsheet`.
- If you encounter permission or missing extension errors, make sure all PHP extensions required by Laravel and the Excel packages are installed.

## Troubleshooting
- **Dependencies not found:** Run `composer install` again.
- **Environment issues:** Double-check your `.env` configuration.
- **Command not found:** Ensure you are running the command from the project root and that `artisan` is executable.

## License
MIT
