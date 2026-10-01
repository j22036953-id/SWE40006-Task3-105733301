using System.ComponentModel.DataAnnotations;
using System.Globalization;
using Microsoft.Data.Sqlite;
using RentalMaintenanceApp.Models;

namespace RentalMaintenanceApp.Services;

public sealed class MaintenanceStore
{
    private readonly string _connectionString;

    public MaintenanceStore(
        IWebHostEnvironment environment,
        IConfiguration configuration)
    {
        // An explicit configuration setting takes priority.
        var configuredPath = configuration["Maintenance:DataDirectory"];
        var azureAppName =
            Environment.GetEnvironmentVariable("WEBSITE_SITE_NAME");

        string directory;

        if (!string.IsNullOrWhiteSpace(configuredPath))
        {
            directory = configuredPath;
        }
        else if (!string.IsNullOrWhiteSpace(azureAppName))
        {
            // Azure: store data outside the deployed website folder.
            var azureHome = Environment.GetEnvironmentVariable("HOME");

            if (string.IsNullOrWhiteSpace(azureHome))
            {
                throw new InvalidOperationException(
                    "Azure HOME is unavailable. Configure Maintenance:DataDirectory.");
            }

            directory = Path.Combine(azureHome, "data", "RentalCare");
        }
        else
        {
            // Local development: retain the existing database.
            directory = Path.Combine(
                environment.ContentRootPath,
                "App_Data");
        }

        if (!Path.IsPathRooted(directory))
        {
            throw new InvalidOperationException(
                "The maintenance data directory must be an absolute path.");
        }

        Directory.CreateDirectory(directory);

        _connectionString = new SqliteConnectionStringBuilder
        {
            DataSource = Path.Combine(directory, "maintenance.db"),
            Mode = SqliteOpenMode.ReadWriteCreate,
            DefaultTimeout = 15
        }.ToString();

        Initialize();
    }

    private SqliteConnection OpenConnection()
    {
        var connection = new SqliteConnection(_connectionString);
        connection.Open();
        return connection;
    }

    private void Initialize()
    {
        using var connection = OpenConnection();
        using var command = connection.CreateCommand();

        command.CommandText = """
            CREATE TABLE IF NOT EXISTS MaintenanceRequests (
                Id INTEGER PRIMARY KEY AUTOINCREMENT,
                Title TEXT NOT NULL,
                Location TEXT NOT NULL,
                Category TEXT NOT NULL,
                Priority TEXT NOT NULL,
                Description TEXT NOT NULL,
                Status TEXT NOT NULL
                    CHECK (Status IN ('Reported', 'InProgress', 'Resolved')),
                CreatedAt TEXT NOT NULL,
                UpdatedAt TEXT NOT NULL
            );
            """;

        command.ExecuteNonQuery();
    }

    public List<MaintenanceRequest> GetAll()
    {
        using var connection = OpenConnection();
        using var command = connection.CreateCommand();

        command.CommandText = """
            SELECT Id, Title, Location, Category, Priority,
                   Description, Status, CreatedAt, UpdatedAt
            FROM MaintenanceRequests
            ORDER BY Id DESC;
            """;

        using var reader = command.ExecuteReader();
        var requests = new List<MaintenanceRequest>();

        while (reader.Read())
        {
            requests.Add(new MaintenanceRequest
            {
                Id = reader.GetInt64(0),
                Title = reader.GetString(1),
                Location = reader.GetString(2),
                Category = Enum.Parse<RequestCategory>(reader.GetString(3)),
                Priority = Enum.Parse<RequestPriority>(reader.GetString(4)),
                Description = reader.GetString(5),
                Status = Enum.Parse<RequestStatus>(reader.GetString(6)),
                CreatedAt = DateTimeOffset.Parse(
                    reader.GetString(7),
                    CultureInfo.InvariantCulture),
                UpdatedAt = DateTimeOffset.Parse(
                    reader.GetString(8),
                    CultureInfo.InvariantCulture)
            });
        }

        return requests;
    }

    public long Create(MaintenanceInput input)
    {
        // Validate again at the storage boundary.
        input.Title = (input.Title ?? "").Trim();
        input.Location = (input.Location ?? "").Trim();
        input.Description = (input.Description ?? "").Trim();

        Validator.ValidateObject(
            input,
            new ValidationContext(input),
            validateAllProperties: true);

        using var connection = OpenConnection();
        using var command = connection.CreateCommand();

        var timestamp = DateTimeOffset.UtcNow.ToString(
            "O",
            CultureInfo.InvariantCulture);

        command.CommandText = """
            INSERT INTO MaintenanceRequests
                (Title, Location, Category, Priority, Description,
                 Status, CreatedAt, UpdatedAt)
            VALUES
                ($title, $location, $category, $priority, $description,
                 'Reported', $createdAt, $updatedAt)
            RETURNING Id;
            """;

        // Parameters keep submitted values separate from SQL instructions.
        command.Parameters.AddWithValue("$title", input.Title);
        command.Parameters.AddWithValue("$location", input.Location);
        command.Parameters.AddWithValue("$category", input.Category.ToString());
        command.Parameters.AddWithValue("$priority", input.Priority.ToString());
        command.Parameters.AddWithValue("$description", input.Description);
        command.Parameters.AddWithValue("$createdAt", timestamp);
        command.Parameters.AddWithValue("$updatedAt", timestamp);

        return Convert.ToInt64(command.ExecuteScalar());
    }

    public bool UpdateStatus(long id, RequestStatus status)
    {
        if (id <= 0 || !Enum.IsDefined(typeof(RequestStatus), status))
        {
            return false;
        }

        using var connection = OpenConnection();
        using var command = connection.CreateCommand();

        command.CommandText = """
            UPDATE MaintenanceRequests
            SET Status = $status, UpdatedAt = $updatedAt
            WHERE Id = $id;
            """;

        command.Parameters.AddWithValue("$status", status.ToString());
        command.Parameters.AddWithValue(
            "$updatedAt",
            DateTimeOffset.UtcNow.ToString(
                "O",
                CultureInfo.InvariantCulture));
        command.Parameters.AddWithValue("$id", id);

        return command.ExecuteNonQuery() == 1;
    }
}