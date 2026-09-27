<?php
declare(strict_types=1);

$fields = [
    'budget' => 'Monthly budget',
    'rent' => 'Rent',
    'food' => 'Food',
    'transport' => 'Transport',
    'study' => 'Study costs',
];

$values = array_fill_keys(array_keys($fields), '');
$errors = [];
$amounts = [];
$result = null;

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int $sen): string
{
    return 'RM ' . number_format($sen / 100, 2, '.', ',');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $key => $label) {
        $raw = $_POST[$key] ?? '';
        $value = is_string($raw) ? trim($raw) : '';
        $values[$key] = $value;

        if ($value === '') {
            $errors[$key] = "Enter {$label}, or 0 if none.";
            continue;
        }

        // Accept non-negative amounts with up to two decimal places.
        if (!preg_match('/\A[0-9]{1,6}(?:\.[0-9]{1,2})?\z/', $value)) {
            $errors[$key] =
                'Enter an amount from 0 to 100,000 with up to two decimal places.';
            continue;
        }

        // Convert the text directly into whole sen.
        $parts = explode('.', $value, 2);
        $ringgit = (int) $parts[0];
        $sen = (int) str_pad($parts[1] ?? '', 2, '0');
        $amount = ($ringgit * 100) + $sen;

        if ($amount > 10000000) {
            $errors[$key] = 'The maximum amount is RM 100,000.00.';
            continue;
        }

        $amounts[$key] = $amount;
    }

    if ($errors === []) {
        $expenses = $amounts['rent']
            + $amounts['food']
            + $amounts['transport']
            + $amounts['study'];

        $remaining = $amounts['budget'] - $expenses;

        $result = [
            'budget' => $amounts['budget'],
            'expenses' => $expenses,
            'remaining' => $remaining,
            'status' => match (true) {
                $remaining > 0 => 'Within budget',
                $remaining === 0 => 'Exactly on budget',
                default => 'Over budget',
            },
            'style' => match (true) {
                $remaining > 0 => 'success',
                $remaining === 0 => 'warning',
                default => 'danger',
            },
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Study Budget Calculator | PHP</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f5f7fb;
            color: #172338;
            font-family: system-ui, sans-serif;
            line-height: 1.5;
        }

        main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        h1 {
            margin: 0 0 12px;
            font-size: clamp(2rem, 5vw, 2.8rem);
        }

        h2 { margin: 0 0 20px; font-size: 1.4rem; }
        .intro { color: #49566b; }
        .badge { color: #4658a8; font-weight: 700; }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start;
            margin-top: 28px;
        }

        .card {
            padding: 24px;
            border: 1px solid #d1d8e3;
            border-radius: 12px;
            background: white;
        }

        .field { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #8995a7;
            border-radius: 6px;
            font: inherit;
        }

        input:focus {
            outline: 3px solid #b6ceff;
            border-color: #2459c4;
        }

        input[aria-invalid="true"] { border-color: #b42318; }
        .error { color: #a51d16; margin: 6px 0 0; }
        .error-summary { margin-bottom: 22px; color: #a51d16; }
        .error-summary a { color: inherit; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }

        button, .clear {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 6px;
            font: inherit;
            cursor: pointer;
        }

        button {
            border: 1px solid #2459c4;
            background: #2459c4;
            color: white;
        }

        button:hover { background: #19479f; }

        .clear {
            color: #344054;
            background: white;
            border: 1px solid #8995a7;
            text-decoration: none;
        }

        button:focus-visible, a:focus-visible {
            outline: 3px solid #2459c4;
            outline-offset: 3px;
        }

        .success { background: #e4f4eb; border-color: #86b89b; }
        .warning { background: #fff5d5; border-color: #d9b958; }
        .danger { background: #fce8e8; border-color: #d99797; }

        dl {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            margin-bottom: 0;
        }

        dt { font-weight: 650; }
        dd { margin: 0; }
        footer { margin-top: 30px; color: #586477; font-size: .9rem; }
        [hidden] { display: none !important; }

        @media (max-width: 700px) {
            .grid { grid-template-columns: 1fr; }
            main { padding: 24px 16px; }
            .card { padding: 20px; }
            dl { grid-template-columns: 1fr; gap: 6px; }
            dd { margin-bottom: 10px; }
        }
    </style>
</head>
<body>
<main>
    <header>
        <p class="badge">PHP student web application</p>
        <h1>Study Budget Calculator</h1>
        <p class="intro">
            Plan your monthly student expenses and check your remaining budget.
        </p>
        <p class="intro" id="amount-help">
            Enter amounts in Malaysian Ringgit (RM), from 0 to 100,000,
            with up to two decimal places. Enter 0 for expenses that do not apply.
        </p>
    </header>

    <div class="grid">
        <section class="card" aria-labelledby="form-heading">
            <h2 id="form-heading">Monthly budget and expenses</h2>

            <?php if ($errors !== []): ?>
                <div class="error-summary" role="alert">
                    <strong>Please correct the following:</strong>
                    <ul>
                        <?php foreach ($errors as $key => $message): ?>
                            <li>
                                <a href="#<?= escape($key) ?>">
                                    <?= escape($fields[$key] . ': ' . $message) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php" id="budget-form">
                <?php foreach ($fields as $key => $label): ?>
                    <div class="field">
                        <label for="<?= escape($key) ?>">
                            <?= escape($label) ?> (RM)
                        </label>
                        <input
                            type="number"
                            id="<?= escape($key) ?>"
                            name="<?= escape($key) ?>"
                            value="<?= escape($values[$key]) ?>"
                            min="0"
                            max="100000"
                            step="0.01"
                            inputmode="decimal"
                            required
                            aria-invalid="<?= isset($errors[$key]) ? 'true' : 'false' ?>"
                            aria-describedby="amount-help<?= isset($errors[$key]) ? ' ' . escape($key) . '-error' : '' ?>"
                        >
                        <?php if (isset($errors[$key])): ?>
                            <p class="error" id="<?= escape($key) ?>-error">
                                <?= escape($errors[$key]) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="actions">
                    <button type="submit">Calculate budget</button>
                    <a href="index.php" class="clear">Clear</a>
                </div>
            </form>
        </section>

        <section aria-label="Budget summary" aria-live="polite">
            <?php if ($result !== null): ?>
                <div
                    id="budget-result"
                    class="card <?= escape($result['style']) ?>"
                >
                    <h2><?= escape($result['status']) ?></h2>
                    <dl>
                        <dt>Monthly budget</dt>
                        <dd><?= money($result['budget']) ?></dd>

                        <dt>Total expenses</dt>
                        <dd><?= money($result['expenses']) ?></dd>

                        <dt>
                            <?= $result['remaining'] < 0
                                ? 'Amount over budget'
                                : 'Remaining budget' ?>
                        </dt>
                        <dd><?= money(abs($result['remaining'])) ?></dd>
                    </dl>
                </div>
            <?php endif; ?>

            <div
                id="budget-placeholder"
                class="card"
                <?= $result !== null ? 'hidden' : '' ?>
            >
                <h2>Your budget summary</h2>
                <p>Complete the form and select Calculate budget to see your results.</p>
            </div>
        </section>
    </div>

    <footer>
        Study Budget Calculator · PHP edition · SWE40006
    </footer>
</main>

<script>
    const form = document.getElementById('budget-form');
    const result = document.getElementById('budget-result');
    const placeholder = document.getElementById('budget-placeholder');

    form.addEventListener('input', () => {
        if (result) {
            result.hidden = true;
        }
        placeholder.hidden = false;
    });
</script>
</body>
</html>