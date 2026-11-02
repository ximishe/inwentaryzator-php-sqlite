<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Inwentaryzator - Mobile & Tablet Ready</title>
    
    <!-- Google Fonts Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom Theme Stylesheet -->
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3 mb-md-4 shadow-sm border-bottom border-secondary border-opacity-25">
    <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <span class="badge bg-primary p-2 rounded-3 text-white"><i class="bi bi-boxes fs-6"></i></span>
            <span class="fs-5 tracking-wide">Inwentaryzator</span>
        </a>
        <button class="navbar-toggler border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Menu nawigacji">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-3 mb-lg-0 pt-2 pt-lg-0">
                <li class="nav-item">
                    <a class="nav-link py-2 <?= (basename($_SERVER['PHP_SELF']) === 'index.php' || basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active text-primary fw-bold' : '' ?>" href="index.php">
                        <i class="bi bi-grid-fill me-1"></i> Sprzęt
                    </a>
                </li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link py-2 <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active text-primary fw-bold' : '' ?>" href="users.php">
                        <i class="bi bi-people me-1"></i> Użytkownicy
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-2 <?= basename($_SERVER['PHP_SELF']) === 'types.php' ? 'active text-primary fw-bold' : '' ?>" href="types.php">
                        <i class="bi bi-tags me-1"></i> Typy sprzętu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-2 <?= basename($_SERVER['PHP_SELF']) === 'settings_fields.php' ? 'active text-primary fw-bold' : '' ?>" href="settings_fields.php">
                        <i class="bi bi-sliders me-1"></i> Ustawienia pól
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-3 pt-lg-0 border-top border-lg-0 border-secondary border-opacity-25">
                <?php if (isset($_SESSION['username'])): ?>
                    <div class="text-secondary small d-flex align-items-center gap-2">
                        <i class="bi bi-person-circle fs-5 text-light"></i>
                        <span>Zalogowano: <strong class="text-light"><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
                        <span class="badge bg-dark border border-secondary text-info text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($_SESSION['role'] ?? 'user') ?></span>
                    </div>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1 px-3 py-1">
                    <i class="bi bi-box-arrow-right"></i> Wyloguj
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-2 px-sm-3 px-md-4 pb-5">
    <?php if (!empty($err)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($ok)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($ok) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
        </div>
    <?php endif; ?>

    <!-- WSTRZYKNIĘCIE WIDOKU -->
    <?= $content ?? '' ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>

<?php if (isset($extra_js)) echo $extra_js; ?>

</body>
</html>
