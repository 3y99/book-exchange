<?php
// Start output buffering
ob_start();
?>
    </main>
    
    <?php if (!isAdmin()): // Show footer only if user is not admin ?>
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?php echo SITE_NAME; ?></h3>
                    <p>Connect with fellow students to buy, sell, and exchange textbooks and academic materials.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>

                

                <div class="footer-section">
                 
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

<?php
// Flush output buffer
ob_end_flush();
?>
