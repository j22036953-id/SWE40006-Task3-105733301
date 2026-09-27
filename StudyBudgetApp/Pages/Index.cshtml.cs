using System.ComponentModel.DataAnnotations;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.RazorPages;

namespace StudyBudgetApp.Pages;

public class IndexModel : PageModel
{
    [BindProperty]
    public BudgetInput Input { get; set; } = new();

    public bool HasResult { get; private set; }
    public decimal TotalExpenses { get; private set; }
    public decimal RemainingBudget { get; private set; }
    public string BudgetStatus { get; private set; } = "";

    public void OnGet()
    {
    }

    public void OnPost()
    {
        // Reject missing, invalid, or out-of-range inputs.
        if (!ModelState.IsValid)
        {
            return;
        }

        TotalExpenses =
            Input.Rent!.Value +
            Input.Food!.Value +
            Input.Transport!.Value +
            Input.StudyCosts!.Value;

        RemainingBudget = Input.MonthlyBudget!.Value - TotalExpenses;

        BudgetStatus = RemainingBudget switch
        {
            > 0 => "Within budget",
            0 => "Exactly on budget",
            < 0 => "Over budget"
        };

        HasResult = true;
    }

    public class BudgetInput
    {
        [Required(ErrorMessage = "Enter your monthly budget.")]
        [Range(typeof(decimal), "0", "100000",
            ErrorMessage = "Enter an amount between RM 0 and RM 100,000.")]
        [Display(Name = "Monthly budget (RM)")]
        public decimal? MonthlyBudget { get; set; }

        [Required(ErrorMessage = "Enter rent, or 0 if none.")]
        [Range(typeof(decimal), "0", "100000",
            ErrorMessage = "Enter an amount between RM 0 and RM 100,000.")]
        [Display(Name = "Rent (RM)")]
        public decimal? Rent { get; set; }

        [Required(ErrorMessage = "Enter food costs, or 0 if none.")]
        [Range(typeof(decimal), "0", "100000",
            ErrorMessage = "Enter an amount between RM 0 and RM 100,000.")]
        [Display(Name = "Food (RM)")]
        public decimal? Food { get; set; }

        [Required(ErrorMessage = "Enter transport costs, or 0 if none.")]
        [Range(typeof(decimal), "0", "100000",
            ErrorMessage = "Enter an amount between RM 0 and RM 100,000.")]
        [Display(Name = "Transport (RM)")]
        public decimal? Transport { get; set; }

        [Required(ErrorMessage = "Enter study costs, or 0 if none.")]
        [Range(typeof(decimal), "0", "100000",
            ErrorMessage = "Enter an amount between RM 0 and RM 100,000.")]
        [Display(Name = "Study costs (RM)")]
        public decimal? StudyCosts { get; set; }
    }
}