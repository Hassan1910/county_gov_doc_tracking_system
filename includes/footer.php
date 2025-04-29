    </div><!-- End of Main Content Container -->

    <!-- Footer -->
    <footer class="bg-primary-800 text-white mt-12">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <div class="flex items-center">
                        <i class="fas fa-file-alt text-xl mr-2"></i>
                        <span class="font-bold">County Gov Document Tracker</span>
                    </div>
                    <p class="text-sm mt-1">Efficient document tracking for county government offices</p>
                </div>
                
                <div class="text-sm">
                    &copy; <?= date('Y'); ?> County Government. All rights reserved.
                </div>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript for Flash Message Dismissal -->
    <script>
        // Auto dismiss flash messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
                alerts.forEach(function(alert) {
                    alert.style.display = 'none';
                });
            }, 5000);
        });
    </script>
</body>
</html> 