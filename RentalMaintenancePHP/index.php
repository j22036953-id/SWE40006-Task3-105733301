<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function textValue(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function textLength(string $value): int
{
    $length = preg_match_all('/./us', $value);
    return $length === false ? strlen($value) : $length;
}

function reference(int $id): string
{
    return sprintf('REQ-%04d', $id);
}

function selected(string $actual, string $expected): string
{
    return $actual === $expected ? ' selected' : '';
}

function redirectWithMessage(string $message): never
{
    $_SESSION['success'] = $message;
    header('Location: index.php', true, 303);
    exit;
}

$categories = [
    'Plumbing',
    'Electrical',
    'Appliances',
    'Furniture',
    'Structural',
    'Other',
];

$priorities = ['Low', 'Medium', 'High'];

$statuses = [
    'Reported' => 'Reported',
    'InProgress' => 'In progress',
    'Resolved' => 'Resolved',
];

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

$errors = [];
$pageError = '';
$showCreate = false;

$input = [
    'title' => '',
    'location' => '',
    'category' => 'Other',
    'priority' => 'Medium',
    'description' => '',
];

try {
    // Keep the database outside the public application folder.
    $configuredDirectory = getenv('MAINTENANCE_DATA_DIRECTORY');

    if (is_string($configuredDirectory) && $configuredDirectory !== '') {
        $dataDirectory = $configuredDirectory;
    } elseif (getenv('WEBSITE_SITE_NAME')) {
        $azureHome = getenv('HOME');

        if (!$azureHome) {
            throw new RuntimeException('Azure HOME is unavailable.');
        }

        $dataDirectory =
            rtrim($azureHome, '/\\') . '/data/RentalCarePHP';
    } else {
        $dataDirectory =
            dirname(__DIR__) . '/RentalMaintenancePHP_Data';
    }

    $configuredDirectory = getenv('MAINTENANCE_DATA_DIRECTORY');

    if (is_string($configuredDirectory) && $configuredDirectory !== '') {
        $dataDirectory = $configuredDirectory;
    } elseif (
        PHP_OS_FAMILY === 'Linux'
        && is_dir('/home/site/wwwroot')
    ) {
        // Azure: persistent storage outside the public website.
        $dataDirectory = '/home/data/RentalCarePHP';
    } else {
        // Local development: retain the existing database.
        $dataDirectory = dirname(__DIR__) . '/RentalMaintenancePHP_Data';
    }

    if (
        !is_dir($dataDirectory)
        && !mkdir($dataDirectory, 0700, true)
        && !is_dir($dataDirectory)
    ) {
        throw new RuntimeException('Cannot create the data directory.');
    }

    $db = new PDO(
        'sqlite:' . $dataDirectory . '/maintenance.db',
        null,
        null,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 15,
        ]
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS maintenance_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            location TEXT NOT NULL,
            category TEXT NOT NULL,
            priority TEXT NOT NULL
                CHECK (priority IN ('Low', 'Medium', 'High')),
            description TEXT NOT NULL,
            status TEXT NOT NULL
                CHECK (status IN ('Reported', 'InProgress', 'Resolved')),
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = textValue($_POST, 'action');

        if (!hash_equals($csrf, textValue($_POST, 'csrf'))) {
            http_response_code(403);
            $pageError =
                'Your form has expired. Refresh the page and try again.';
        } elseif ($action === 'create') {
            $showCreate = true;

            foreach (array_keys($input) as $key) {
                $input[$key] = textValue($_POST, $key);
            }

            $titleLength = textLength($input['title']);
            $locationLength = textLength($input['location']);
            $descriptionLength = textLength($input['description']);

            if ($titleLength < 5 || $titleLength > 100) {
                $errors['title'] =
                    'Enter an issue title between 5 and 100 characters.';
            }

            if ($locationLength < 1 || $locationLength > 60) {
                $errors['location'] =
                    'Enter a room or location of up to 60 characters.';
            }

            if (!in_array($input['category'], $categories, true)) {
                $errors['category'] = 'Choose a valid category.';
            }

            if (!in_array($input['priority'], $priorities, true)) {
                $errors['priority'] = 'Choose a valid priority.';
            }

            if ($descriptionLength < 10 || $descriptionLength > 2000) {
                $errors['description'] =
                    'Describe the problem using 10 to 2,000 characters.';
            }

            if ($errors === []) {
                $now = gmdate('Y-m-d\TH:i:s\Z');

                $statement = $db->prepare(
                    "INSERT INTO maintenance_requests
                        (title, location, category, priority, description,
                         status, created_at, updated_at)
                     VALUES
                        (:title, :location, :category, :priority, :description,
                         'Reported', :created_at, :updated_at)"
                );

                $statement->execute([
                    'title' => $input['title'],
                    'location' => $input['location'],
                    'category' => $input['category'],
                    'priority' => $input['priority'],
                    'description' => $input['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $newId = (int) $db->lastInsertId();

                redirectWithMessage(
                    'Request ' . reference($newId) .
                    ' was created successfully.'
                );
            }
        } elseif ($action === 'update') {
            $id = filter_var(
                textValue($_POST, 'id'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            $newStatus = textValue($_POST, 'newStatus');

            if ($id === false || !array_key_exists($newStatus, $statuses)) {
                $pageError = 'Choose a valid request and status.';
            } else {
                $statement = $db->prepare(
                    'UPDATE maintenance_requests
                     SET status = :status, updated_at = :updated_at
                     WHERE id = :id'
                );

                $statement->execute([
                    'status' => $newStatus,
                    'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
                    'id' => $id,
                ]);

                if ($statement->rowCount() > 0) {
                    redirectWithMessage(
                        'Request ' . reference($id) . ' was updated.'
                    );
                }

                $pageError = 'That request could not be found.';
            }
        } else {
            $pageError = 'The requested action is not available.';
        }
    }

    $allRequests = $db->query(
        'SELECT * FROM maintenance_requests ORDER BY id DESC'
    )->fetchAll();
} catch (Throwable $exception) {
    error_log('RentalCare PHP: ' . $exception->getMessage());
    http_response_code(500);

    exit(
        '<!doctype html><html lang="en"><meta charset="utf-8">' .
        '<title>RentalCare unavailable</title>' .
        '<h1>RentalCare is temporarily unavailable</h1>' .
        '<p>The application could not access its data. ' .
        'Check the PHP or Azure application logs, then try again.</p></html>'
    );
}

$totalCount = count($allRequests);
$reportedCount = 0;
$inProgressCount = 0;
$resolvedCount = 0;

foreach ($allRequests as $request) {
    match ($request['status']) {
        'Reported' => $reportedCount++,
        'InProgress' => $inProgressCount++,
        'Resolved' => $resolvedCount++,
    };
}

$openCount = $reportedCount + $inProgressCount;

$search = textValue($_GET, 'Search');
$statusFilter = textValue($_GET, 'StatusFilter');
$priorityFilter = textValue($_GET, 'PriorityFilter');

if (!array_key_exists($statusFilter, $statuses)) {
    $statusFilter = '';
}

if (!in_array($priorityFilter, $priorities, true)) {
    $priorityFilter = '';
}

$requests = array_values(array_filter(
    $allRequests,
    function (array $request) use (
        $search,
        $statusFilter,
        $priorityFilter
    ): bool {
        $searchable =
            reference((int) $request['id']) . ' ' .
            $request['title'] . ' ' . $request['location'];

        return (
            ($search === '' || stripos($searchable, $search) !== false)
            && ($statusFilter === '' || $request['status'] === $statusFilter)
            && ($priorityFilter === '' || $request['priority'] === $priorityFilter)
        );
    }
));

$stats = [
    ['Total requests', $totalCount, 'neutral', '▤', 'All recorded maintenance'],
    ['Reported', $reportedCount, 'amber', '!', 'Awaiting action'],
    ['In progress', $inProgressCount, 'blue', '↗', 'Work is underway'],
    ['Resolved', $resolvedCount, 'teal', '✓', 'Closed with care'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance overview | RentalCare</title>
    <link rel="stylesheet" href="assets/maintenance.css">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>

<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="index.php" aria-label="RentalCare home">
            <span class="brand-mark" aria-hidden="true">R</span>
            <span>Rental<span class="brand-accent">Care</span></span>
        </a>

        <div class="workspace">
            <span class="workspace-icon" aria-hidden="true">⌂</span>
            <div>
                <strong>Rental workspace</strong>
                <span>Property maintenance</span>
            </div>
        </div>

        <p class="nav-label">WORKSPACE</p>

        <nav aria-label="Main navigation">
            <a class="nav-item active" href="index.php" aria-current="page">
                <span aria-hidden="true">▦</span>
                Overview
            </a>
            <a class="nav-item" href="#requests">
                <span aria-hidden="true">☷</span>
                Maintenance requests
            </a>
            <a class="nav-item" href="#create-request" data-open-request>
                <span aria-hidden="true">＋</span>
                Report an issue
            </a>
        </nav>

        <div class="sidebar-note">
            <span class="small-label">A LITTLE CARE GOES A LONG WAY</span>
            <p>Keep every repair moving, from the first report to resolution.</p>
        </div>

        <div class="workspace-footer">
            <span class="avatar" aria-hidden="true">D</span>
            <div>
                <strong>Demonstration workspace</strong>
                <span>Use sample information only</span>
            </div>
        </div>
    </aside>

    <main id="main-content" class="main">
        <header class="topbar">
            <span>
                Workspace <span class="breadcrumb-divider">/</span>
                <strong>Overview</strong>
            </span>
            <span class="demo-badge">Single-property demo</span>
        </header>

        <div class="content">
            <section class="page-heading" aria-labelledby="page-title">
                <div>
                    <p class="eyebrow">PROPERTY CARE, MADE CLEAR</p>
                    <h1 id="page-title">Maintenance overview</h1>
                    <p class="muted">
                        Manage repairs, track progress, and keep your property in good shape.
                    </p>
                </div>
                <button class="button primary" type="button" data-open-request>
                    <span aria-hidden="true">＋</span> Report an issue
                </button>
            </section>

            <?php if ($success !== ''): ?>
                <div class="notice success" role="status">
                    <?= e($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($pageError !== ''): ?>
                <div class="notice error" role="alert">
                    <?= e($pageError) ?>
                </div>
            <?php endif; ?>

            <section class="stats" aria-label="Maintenance totals">
                <?php foreach ($stats as [$label, $count, $colour, $symbol, $caption]): ?>
                    <article class="stat-card">
                        <div class="stat-top">
                            <span><?= e($label) ?></span>
                            <span class="stat-symbol <?= e($colour) ?>"
                                  aria-hidden="true"><?= e($symbol) ?></span>
                        </div>
                        <strong class="stat-number"><?= $count ?></strong>
                        <span class="stat-caption"><?= e($caption) ?></span>
                    </article>
                <?php endforeach; ?>
            </section>

            <details id="create-request" class="panel create-panel"
                     <?= $showCreate ? 'open' : '' ?>>
                <summary>
                    <span>
                        <strong>Report a maintenance issue</strong>
                        <span class="summary-description">
                            Tell us what needs attention.
                        </span>
                    </span>
                    <span class="expand-icon" aria-hidden="true">＋</span>
                </summary>

                <div class="panel-body">
                    <form method="post" action="index.php">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                        <?php if ($errors !== []): ?>
                            <div class="validation-summary" role="alert">
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="field">
                                <label for="title">Issue title</label>
                                <input id="title" name="title"
                                       value="<?= e($input['title']) ?>"
                                       minlength="5" maxlength="100" required
                                       placeholder="e.g. Kitchen tap is leaking"
                                       aria-describedby="title-error"
                                       aria-invalid="<?= isset($errors['title']) ? 'true' : 'false' ?>"
                                       class="<?= isset($errors['title']) ? 'input-validation-error' : '' ?>">
                                <span id="title-error" class="field-error">
                                    <?= e($errors['title'] ?? '') ?>
                                </span>
                            </div>

                            <div class="field">
                                <label for="location">Room or location</label>
                                <input id="location" name="location"
                                       value="<?= e($input['location']) ?>"
                                       maxlength="60" required
                                       placeholder="e.g. Unit 2 · Kitchen"
                                       aria-describedby="location-error"
                                       aria-invalid="<?= isset($errors['location']) ? 'true' : 'false' ?>"
                                       class="<?= isset($errors['location']) ? 'input-validation-error' : '' ?>">
                                <span id="location-error" class="field-error">
                                    <?= e($errors['location'] ?? '') ?>
                                </span>
                            </div>

                            <div class="field">
                                <label for="category">Category</label>
                                <select id="category" name="category"
                                        aria-describedby="category-error">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= e($category) ?>"
                                            <?= selected($input['category'], $category) ?>>
                                            <?= e($category) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span id="category-error" class="field-error">
                                    <?= e($errors['category'] ?? '') ?>
                                </span>
                            </div>

                            <div class="field">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority"
                                        aria-describedby="priority-help priority-error">
                                    <?php foreach ($priorities as $priority): ?>
                                        <option value="<?= e($priority) ?>"
                                            <?= selected($input['priority'], $priority) ?>>
                                            <?= e($priority) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="priority-help" class="muted">
                                    High: urgent attention · Medium: routine repair · Low: minor issue
                                </small>
                                <span id="priority-error" class="field-error">
                                    <?= e($errors['priority'] ?? '') ?>
                                </span>
                            </div>

                            <div class="field full-width">
                                <label for="description">Description</label>
                                <textarea id="description" name="description"
                                          rows="4" minlength="10" maxlength="2000"
                                          required
                                          placeholder="Describe the problem, when it started, and any useful details."
                                          aria-describedby="description-error"
                                          aria-invalid="<?= isset($errors['description']) ? 'true' : 'false' ?>"
                                          class="<?= isset($errors['description']) ? 'input-validation-error' : '' ?>"><?= e($input['description']) ?></textarea>
                                <span id="description-error" class="field-error">
                                    <?= e($errors['description'] ?? '') ?>
                                </span>
                            </div>
                        </div>

                        <div class="form-footer">
                            <p class="muted">
                                New requests start with the status <strong>Reported</strong>.
                            </p>
                            <button type="submit" class="button primary">
                                Submit request <span aria-hidden="true">↗</span>
                            </button>
                        </div>
                    </form>
                </div>
            </details>

            <section id="requests" class="panel" aria-labelledby="requests-heading">
                <div class="panel-heading">
                    <div>
                        <h2 id="requests-heading">Maintenance requests</h2>
                        <p class="muted">Track the details. Keep the work moving.</p>
                    </div>
                    <span class="count-badge"><?= $openCount ?> open</span>
                </div>

                <form method="get" action="index.php#requests"
                      class="filter-bar" role="search">
                    <div class="field search-field">
                        <label for="Search">Search requests</label>
                        <input id="Search" name="Search" type="search"
                               value="<?= e($search) ?>"
                               placeholder="Search title, location or reference…">
                    </div>

                    <div class="field">
                        <label for="StatusFilter">Status</label>
                        <select id="StatusFilter" name="StatusFilter">
                            <option value="">All statuses</option>
                            <?php foreach ($statuses as $value => $label): ?>
                                <option value="<?= e($value) ?>"
                                    <?= selected($statusFilter, $value) ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="PriorityFilter">Priority</label>
                        <select id="PriorityFilter" name="PriorityFilter">
                            <option value="">All priorities</option>
                            <?php foreach ($priorities as $priority): ?>
                                <option value="<?= e($priority) ?>"
                                    <?= selected($priorityFilter, $priority) ?>>
                                    <?= e($priority) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="button secondary">Apply</button>
                    <a href="index.php#requests" class="reset-link">Reset</a>
                </form>

                <?php if ($requests === []): ?>
                    <div class="empty-state">
                        <span class="empty-icon" aria-hidden="true">⌂</span>

                        <?php if ($totalCount === 0): ?>
                            <h3>A well-kept home starts here.</h3>
                            <p>Report your first issue to start tracking maintenance.</p>
                            <button type="button" class="button primary" data-open-request>
                                Report your first issue
                            </button>
                        <?php else: ?>
                            <h3>No matching requests</h3>
                            <p>Try a different search or clear your filters.</p>
                            <a href="index.php#requests" class="button secondary">
                                Clear filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="request-list">
                        <?php foreach ($requests as $request): ?>
                            <?php
                            $requestId = (int) $request['id'];
                            $requestReference = reference($requestId);
                            ?>
                            <article class="request-card">
                                <div class="request-main">
                                    <div class="request-meta">
                                        <span class="reference">
                                            <?= e($requestReference) ?>
                                        </span>
                                        <span class="category">
                                            <?= e($request['category']) ?>
                                        </span>
                                    </div>

                                    <h3><?= e($request['title']) ?></h3>
                                    <p class="location"><?= e($request['location']) ?></p>

                                    <div class="request-tags">
                                        <span class="status-badge <?= e(strtolower($request['status'])) ?>">
                                            <span class="status-dot" aria-hidden="true"></span>
                                            <?= e($statuses[$request['status']]) ?>
                                        </span>
                                        <span class="priority-badge <?= e(strtolower($request['priority'])) ?>">
                                            <?= e($request['priority']) ?> priority
                                        </span>
                                    </div>

                                    <details class="request-details">
                                        <summary>View description</summary>
                                        <p><?= nl2br(e($request['description'])) ?></p>
                                        <span class="muted">
                                            Last updated:
                                            <?= e(gmdate('d M Y, H:i', strtotime($request['updated_at']))) ?>
                                            UTC
                                        </span>
                                    </details>
                                </div>

                                <div class="request-actions">
                                    <span class="request-date">
                                        Reported
                                        <?= e(gmdate('d M Y', strtotime($request['created_at']))) ?>
                                    </span>

                                    <form method="post" action="index.php" class="status-form">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= $requestId ?>">

                                        <label for="status-<?= $requestId ?>">Update status</label>

                                        <div class="status-controls">
                                            <select id="status-<?= $requestId ?>" name="newStatus">
                                                <?php foreach ($statuses as $value => $label): ?>
                                                    <option value="<?= e($value) ?>"
                                                        <?= selected($request['status'], $value) ?>>
                                                        <?= e($label) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button type="submit" class="button secondary"
                                                    aria-label="Save status for <?= e($requestReference) ?>">
                                                Save
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="list-footer">
                        Showing <?= count($requests) ?> of <?= $totalCount ?> requests
                        <span>Newest first</span>
                    </div>
                <?php endif; ?>
            </section>

            <footer class="page-footer">
                <span>RentalCare · Rental Maintenance Tracker</span>
                <span>PHP edition · SWE40006</span>
            </footer>
        </div>
    </main>
</div>

<script>
const createPanel = document.getElementById('create-request');

document.querySelectorAll('[data-open-request]').forEach(trigger => {
    trigger.addEventListener('click', event => {
        event.preventDefault();
        createPanel.open = true;
        createPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        createPanel.querySelector('input:not([type="hidden"])')
            ?.focus({ preventScroll: true });
    });
});
</script>
</body>
</html>