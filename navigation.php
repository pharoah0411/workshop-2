<!-- navigation.php -->
<div class="oval-nav-container" id="ovalNav">
    <!-- MOBILE MENU TOGGLE -->
    <button class="mobile-menu-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <nav class="oval-nav" id="ovalNavInner">
        <!-- NAVIGATION MENU -->
        <ul class="nav-menu" id="navMenu">
            <!-- DASHBOARD -->
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo $active_menu == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            
            <!-- INVENTORY MANAGEMENT -->
            <li class="nav-item <?php echo $active_menu == 'inventory' ? 'active' : ''; ?>">
                <a href="#" class="nav-link <?php echo $active_menu == 'inventory' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
                <div class="dropdown-menu">
                    <div class="dropdown-header">Inventory Management</div>
                    <a href="medDirectory.php" class="dropdown-item <?php echo $current_page == 'medDirectory.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list-alt"></i> Medicine List
                    </a>
                    <a href="inventory.php" class="dropdown-item <?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>">
                        <i class="fas fa-boxes"></i> Stock Management
                    </a>
                    <a href="low_stock.php" class="dropdown-item <?php echo $current_page == 'low_stock.php' ? 'active' : ''; ?>">
                        <i class="fas fa-exclamation-triangle"></i> Low Stock Alert
                    </a>
                </div>
            </li>
            
            <!-- Continue with all other menu items... -->
        </ul>
        
        <!-- USER SECTION -->
        <div class="user-section">
            <div class="user-badge">
                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($userRole); ?>)
            </div>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </button>
        </div>
    </nav>
</div><!-- navigation.php -->
<div class="oval-nav-container" id="ovalNav">
    <!-- MOBILE MENU TOGGLE -->
    <button class="mobile-menu-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <nav class="oval-nav" id="ovalNavInner">
        <!-- NAVIGATION MENU -->
        <ul class="nav-menu" id="navMenu">
            <!-- DASHBOARD -->
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo $active_menu == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            
            <!-- INVENTORY MANAGEMENT -->
            <li class="nav-item <?php echo $active_menu == 'inventory' ? 'active' : ''; ?>">
                <a href="#" class="nav-link <?php echo $active_menu == 'inventory' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
                <div class="dropdown-menu">
                    <div class="dropdown-header">Inventory Management</div>
                    <a href="medDirectory.php" class="dropdown-item <?php echo $current_page == 'medDirectory.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list-alt"></i> Medicine List
                    </a>
                    <a href="inventory.php" class="dropdown-item <?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>">
                        <i class="fas fa-boxes"></i> Stock Management
                    </a>
                    <a href="low_stock.php" class="dropdown-item <?php echo $current_page == 'low_stock.php' ? 'active' : ''; ?>">
                        <i class="fas fa-exclamation-triangle"></i> Low Stock Alert
                    </a>
                </div>
            </li>
            
            <!-- Continue with all other menu items... -->
        </ul>
        
        <!-- USER SECTION -->
        <div class="user-section">
            <div class="user-badge">
                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($userRole); ?>)
            </div>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </button>
        </div>
    </nav>
</div>