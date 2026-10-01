# SWE40006 Deployment Portfolio — Task 3

**Student:** Teoh Thong Yee  
**Student ID:** 105733301  
**Unit:** SWE40006 Software Deployment and Evolution  
**Semester:** August 2026  
**Level attempted:** High Distinction — Sub-tasks 3.1, 3.2 and 3.3

## Applications

| Task | Application | Technology |
|---|---|---|
| 3.1 | Existing Hello World / Counter sample | ASP.NET Core |
| 3.2 | RentalCare — Rental Maintenance Tracker | C# ASP.NET Core Razor Pages, .NET 10 and SQLite |
| 3.3 | RentalCare — Rental Maintenance Tracker | PHP 8.3, PDO SQLite, HTML, CSS and JavaScript |

RentalCare is a demonstration application for recording rental-property maintenance issues and tracking their progress from Reported to In progress and Resolved.

The C# and PHP editions share a consistent responsive design but use separate implementations and databases.

## Live Azure websites

- [Existing C# sample — Task 3.1](https://thongyee-task3-sample-105733301-cec4adeegcbvb4a4.centralindia-01.azurewebsites.net/)
- [RentalCare C# edition — Task 3.2](https://thongyee-task3-budget-105733301-hmguh2gzf2e6a2e9.centralindia-01.azurewebsites.net/)
- [RentalCare PHP edition — Task 3.3](https://thongyee-task3-php-105733301-d0hje0baeybjdvec.centralindia-01.azurewebsites.net/)

The applications are deployed to Azure App Service in Central India. The custom C# application uses Windows hosting, and the PHP application uses Linux hosting with PHP 8.3.

The C# site's address retains “budget” because its existing Azure App Service was reused for RentalCare.

The C# application was stopped to demonstrate deactivation, then started again to restore public access.

## Repository structure

- `RentalMaintenanceApp/` — current C# RentalCare source.
- `RentalMaintenancePHP/` — current PHP RentalCare source.
- `StudyBudgetApp/` — earlier C# budget-calculator prototype.
- `StudyBudgetPHP/` — earlier PHP budget-calculator prototype.
- `.gitignore` — excludes build output, runtime databases, local workspace settings and deployment profiles.

The RentalCare folders contain the current custom applications submitted for Tasks 3.2 and 3.3.

## RentalCare features

- Create maintenance requests with a title, location, category, priority and description.
- Generate request references such as `REQ-0001`.
- Update requests through Reported, In progress and Resolved statuses.
- Display total requests and counts for each status.
- Search by title, location or request reference.
- Filter requests by status and priority.
- Store requests in SQLite.
- Validate submitted fields on the server.
- Use parameterised SQL queries and escape displayed user input.
- Protect POST forms with anti-forgery tokens.
- Adapt the layout for desktop and mobile screens.

This is an anonymous, single-property coursework demonstration. It does not implement user accounts or role-based access. Use sample information only.

## Run the C# application locally

Prerequisite: .NET 10 SDK.

From the repository root:

```powershell
cd RentalMaintenanceApp
dotnet restore
dotnet run
```

Open the localhost URL printed in the terminal.

The application creates its local SQLite database in `App_Data`. That directory is excluded from version control and publishing.

To produce a Release deployment:

```powershell
dotnet publish -c Release -o ./publish
```

Deploy the published output to the C# Azure App Service.

## Run the PHP application locally

Prerequisites: PHP 8.3 and the PDO SQLite extension.

Verify SQLite support:

```powershell
php -r "print_r(PDO::getAvailableDrivers());"
```

The output must include `sqlite`.

From the repository root:

```powershell
cd RentalMaintenancePHP
php -l index.php
php -S localhost:8000
```

Open [the local PHP application](http://localhost:8000/index.php).

The application creates its local database in `RentalMaintenancePHP_Data`, beside the PHP project folder and outside the public website directory.

The PHP built-in server is for local development. The deployed application uses Azure's PHP runtime and Nginx.

## Azure database storage

Runtime databases are separate from application deployment files:

- C#: uses `HOME/data/RentalCare` on Azure unless `Maintenance:DataDirectory` is explicitly configured.
- PHP: uses `/home/data/RentalCarePHP` on Azure Linux unless `MAINTENANCE_DATA_DIRECTORY` is explicitly configured.

For a fresh PHP deployment, ensure the database directory exists and is writable by the PHP process.

Local test databases are not uploaded. Each deployed application maintains its own requests.

## Testing and evidence

Testing included:

- Creating maintenance requests.
- Updating request statuses and checking dashboard counts.
- Rejecting empty submissions through server-side validation.
- Checking C# status filters, including an empty-result state.
- Reviewing responsive layouts at a 390-pixel viewport.
- Checking saved records after local server restarts.
- Creating and updating requests on Azure.
- Stopping and starting the C# Azure application and checking its retained request.

Screenshots, deployment evidence and detailed test results are included in the assignment report.

## Troubleshooting

### Local PHP SQLite configuration

PHP initially loaded no configuration file and reported no PDO database drivers. A `php.ini` file was created from the development template. The extension directory was configured, and `pdo_sqlite` was enabled.

Verification showed `sqlite` in `PDO::getAvailableDrivers()`.

### PHP deployment displayed a 404

The Azure deployment completed, but the browser displayed an Nginx 404 page.

Investigation confirmed that:

- `index.php` and the assets folder existed in the deployment directory.
- The CSS file returned HTTP 200.
- The deployed PHP file passed its syntax check.
- Azure Log stream recorded `mkdir(): Permission denied` and `Cannot create the data directory`.
- PHP returned HTTP 500, which was obscured by the server's error-page handling.

The database path was changed to the explicit Azure location `/home/data/RentalCarePHP`. The directory was prepared and checked for write access before redeployment. The application then loaded successfully and accepted maintenance requests.

## Existing sample attribution

The Task 3.1 application was obtained from [Microsoft's Azure Samples repository](https://github.com/Azure-Samples/dotnetcore-docs-hello-world).

That sample is separate from the custom RentalCare applications in this repository.