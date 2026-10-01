using System.ComponentModel.DataAnnotations;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.RazorPages;
using RentalMaintenanceApp.Models;
using RentalMaintenanceApp.Services;

namespace RentalMaintenanceApp.Pages;

public class IndexModel : PageModel
{
    private readonly MaintenanceStore _store;

    public IndexModel(MaintenanceStore store)
    {
        _store = store;
    }

    [BindProperty]
    public MaintenanceInput Input { get; set; } = new();

    [BindProperty(SupportsGet = true)]
    public string? Search { get; set; }

    [BindProperty(SupportsGet = true)]
    public RequestStatus? StatusFilter { get; set; }

    [BindProperty(SupportsGet = true)]
    public RequestPriority? PriorityFilter { get; set; }

    [TempData]
    public string? SuccessMessage { get; set; }

    [TempData]
    public string? ErrorMessage { get; set; }

    public List<MaintenanceRequest> Requests { get; private set; } = [];

    public int TotalCount { get; private set; }
    public int ReportedCount { get; private set; }
    public int InProgressCount { get; private set; }
    public int ResolvedCount { get; private set; }
    public int OpenCount => ReportedCount + InProgressCount;

    public bool ShowCreateForm { get; private set; }

    public void OnGet()
    {
        LoadDashboard();
    }

    public IActionResult OnPostCreate()
    {
        // Trim before validation so spaces do not satisfy minimum lengths.
        Input.Title = (Input.Title ?? "").Trim();
        Input.Location = (Input.Location ?? "").Trim();
        Input.Description = (Input.Description ?? "").Trim();

        ModelState.Clear();
        TryValidateModel(Input, nameof(Input));

        if (!ModelState.IsValid)
        {
            ShowCreateForm = true;
            LoadDashboard();
            return Page();
        }

        try
        {
            var id = _store.Create(Input);
            SuccessMessage = $"Request REQ-{id:D4} was created successfully.";

            // Redirect after saving to prevent duplicate submissions on refresh.
            return RedirectToPage();
        }
        catch (ValidationException)
        {
            ModelState.AddModelError(
                string.Empty,
                "Please check the request details and try again.");

            ShowCreateForm = true;
            LoadDashboard();
            return Page();
        }
    }

    public IActionResult OnPostUpdateStatus(
        long id,
        RequestStatus? newStatus)
    {
        // Only the request ID and status are relevant to this action.
        // The new-request form is not submitted by the status form.
        var validStatus = newStatus.HasValue
            && Enum.IsDefined(
                typeof(RequestStatus),
                newStatus.Value);

        if (id <= 0 || !validStatus)
        {
            ErrorMessage = "Choose a valid request and status.";
            return RedirectToPage();
        }

        var updated = _store.UpdateStatus(id, newStatus!.Value);

        if (updated)
        {
            SuccessMessage = $"Request REQ-{id:D4} was updated.";
        }
        else
        {
            ErrorMessage = "That request could not be found. Refresh and try again.";
        }

        return RedirectToPage();
    }

    private void LoadDashboard()
    {
        var allRequests = _store.GetAll();

        // Dashboard totals describe all requests, independent of filters.
        TotalCount = allRequests.Count;
        ReportedCount = allRequests.Count(
            request => request.Status == RequestStatus.Reported);
        InProgressCount = allRequests.Count(
            request => request.Status == RequestStatus.InProgress);
        ResolvedCount = allRequests.Count(
            request => request.Status == RequestStatus.Resolved);

        IEnumerable<MaintenanceRequest> filtered = allRequests;

        if (!string.IsNullOrWhiteSpace(Search))
        {
            var term = Search.Trim();

            filtered = filtered.Where(request =>
                request.Reference.Contains(
                    term, StringComparison.OrdinalIgnoreCase)
                || request.Title.Contains(
                    term, StringComparison.OrdinalIgnoreCase)
                || request.Location.Contains(
                    term, StringComparison.OrdinalIgnoreCase));
        }

        if (StatusFilter.HasValue)
        {
            filtered = filtered.Where(
                request => request.Status == StatusFilter.Value);
        }

        if (PriorityFilter.HasValue)
        {
            filtered = filtered.Where(
                request => request.Priority == PriorityFilter.Value);
        }

        Requests = filtered.ToList();
    }
}