<?php
// Start output buffering
ob_start();
?>
    </main>
    
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?php echo SITE_NAME; ?> Admin</h3>
                    <p>Administration panel for the peer-to-peer book exchange platform.</p>
                </div>
                
                <div class="footer-section">
                    <h4>Admin Links</h4>
                    <ul>
                        <li><a href="index.php">Dashboard</a></li>
                        <li><a href="users.php">User Management</a></li>
                        <li><a href="listings.php">Listing Management</a></li>
                        <li><a href="reports.php">Report Management</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>System</h4>
                    <ul>
                        <li><a href="../pages/">View Main Site</a></li>
                        <li><a href="../pages/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
<?php
// End output buffering and flush
ob_end_flush();