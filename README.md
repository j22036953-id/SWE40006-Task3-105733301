# SWE40006 Deployment Portfolio — Task 3

Student ID: 105733301
Unit: SWE40006 Software Deployment and Evolution

## Applications

| Task | Application | Technology |
|---|---|---|
| 3.1 | Existing Hello World / Counter sample | ASP.NET Core |
| 3.2 | Custom Study Budget Calculator | C# ASP.NET Core Razor Pages |
| 3.3 | Custom Study Budget Calculator | PHP 8.3 |

## Live Azure websites

- [Existing C# sample](https://thongyee-task3-sample-105733301-cec4adeegcbvb4a4.centralindia-01.azurewebsites.net/)
- [Custom C# calculator](https://thongyee-task3-budget-105733301-hmguh2gzf2e6a2e9.centralindia-01.azurewebsites.net/)
- [Custom PHP calculator](https://thongyee-task3-php-105733301-d0hje0baeybjdvec.centralindia-01.azurewebsites.net/)

The applications were deployed to Azure App Service in Central India.
The custom C# application uses Windows hosting. The PHP application
uses Linux hosting with PHP 8.3.

The custom C# application was stopped to demonstrate deactivation,
then started again to restore public access.

## Repository structure

- StudyBudgetApp/ — custom C# application source.
- StudyBudgetPHP/ — custom PHP application source.
- .gitignore — excludes generated build files and deployment profiles.

## Run the C# application locally

Prerequisite: .NET 10 SDK.

From the repository root:

    cd StudyBudgetApp
    dotnet restore
    dotnet run

Open the localhost URL printed in the terminal.

## Run the PHP application locally

Prerequisite: PHP 8.3.

From the repository root:

    cd StudyBudgetPHP
    php -l index.php
    php -S localhost:8000

Open http://localhost:8000/index.php.

The PHP built-in server is used for local development.
The public application is hosted by Azure App Service.

## Calculator features

Both custom applications accept a monthly budget and expenses for
rent, food, transport, and study costs.

They display:
- Total expenses.
- Remaining budget or amount over budget.
- Within budget, exactly on budget, or over budget status.

Both applications validate inputs and hide outdated results when
the user changes an input.

The C# application uses decimal values for money calculations.
The PHP application calculates using integer sen and accepts
amounts with up to two decimal places.

## Testing performed

- Within-budget calculation.
- Over-budget calculation.
- Exactly-on-budget calculation.
- Decimal currency amounts.
- Negative-value rejection in the browser.
- Server-side validation checks.
- Successful calculations on the deployed Azure applications.
- C# application deactivation and restart.

Screenshots and detailed results are provided in the assignment report.

## Existing sample attribution

The Task 3.1 application was obtained from Microsoft's Azure Samples:

https://github.com/Azure-Samples/dotnetcore-docs-hello-world

The existing sample is separate from the two custom applications
included in this repository.