<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="brand-section">
    <div class="logo">E</div>
    <h1 class="brand-name">ExaMatrix</h1>
</div>
<nav class="nav-menu">
    <ul>
        <li class="nav-item">
            <a href="admin_dashboard.php" class="nav-link <?php echo $current_page === 'admin_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="courses.php" class="nav-link <?php echo $current_page === 'courses.php' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>Courses</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="students.php" class="nav-link <?php echo $current_page === 'students.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Students</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="exams.php" class="nav-link <?php echo $current_page === 'exams.php' || $current_page === 'monitor_exam.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Exams</span>
            </a>
        </li>
    </ul>
</nav>
<div class="admin-section">
    <div class="admin-avatar">AD</div>
    <div class="admin-details">
        <div class="admin-name">Admin User</div>
        <div class="admin-role">Administrator</div>
    </div>
    <a href="../logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
    </a>
</div> 