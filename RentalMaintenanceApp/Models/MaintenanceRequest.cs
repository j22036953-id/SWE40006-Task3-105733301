using System.ComponentModel.DataAnnotations;

namespace RentalMaintenanceApp.Models;

public enum RequestCategory
{
    Plumbing,
    Electrical,
    Appliances,
    Furniture,
    Structural,
    Other
}

public enum RequestPriority
{
    Low,
    Medium,
    High
}

public enum RequestStatus
{
    Reported,
    InProgress,
    Resolved
}

// Fields entered by the user when reporting a problem.
public class MaintenanceInput
{
    [Required(ErrorMessage = "Enter a short issue title.")]
    [StringLength(100, MinimumLength = 5,
        ErrorMessage = "Use between 5 and 100 characters.")]
    public string Title { get; set; } = "";

    [Required(ErrorMessage = "Enter the room or location.")]
    [StringLength(60,
        ErrorMessage = "Use no more than 60 characters.")]
    public string Location { get; set; } = "";

    [EnumDataType(typeof(RequestCategory),
        ErrorMessage = "Choose a valid category.")]
    public RequestCategory Category { get; set; } = RequestCategory.Other;

    [EnumDataType(typeof(RequestPriority),
        ErrorMessage = "Choose a valid priority.")]
    public RequestPriority Priority { get; set; } = RequestPriority.Medium;

    [Required(ErrorMessage = "Describe the maintenance problem.")]
    [StringLength(2000, MinimumLength = 10,
        ErrorMessage = "Use between 10 and 2,000 characters.")]
    public string Description { get; set; } = "";
}

// A saved maintenance request.
public class MaintenanceRequest
{
    public long Id { get; set; }
    public string Reference => $"REQ-{Id:D4}";
    public string Title { get; set; } = "";
    public string Location { get; set; } = "";
    public RequestCategory Category { get; set; }
    public RequestPriority Priority { get; set; }
    public string Description { get; set; } = "";
    public RequestStatus Status { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset UpdatedAt { get; set; }

    public string StatusLabel => Status switch
    {
        RequestStatus.InProgress => "In progress",
        RequestStatus.Resolved => "Resolved",
        _ => "Reported"
    };
}