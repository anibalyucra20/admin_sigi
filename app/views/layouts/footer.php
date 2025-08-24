</div> <!-- container-fluid -->
</div>
<!-- End Page-content -->
<?php if ($logueado): ?>
    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    SIGI &copy; <?= date('Y') ?>
                </div>
                <div class="col-sm-6">
                    <div class="text-sm-end d-none d-sm-block">
                        Versión 1.0
                    </div>
                </div>
            </div>
        </div>
    </footer>
<?php endif; ?>
</div>
<!-- end main content-->
</div>
<!-- END layout-wrapper -->
<!-- jQuery (primero) -->
<script src="<?= BASE_URL ?>/assets/js/jquery.min.js"></script>

<!-- Bootstrap 4 bundle (incluye Popper) -->
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>

<!-- DataTables core + Bootstrap 4 -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>

<!-- Extensiones opcionales / otros plugins -->
<script src="<?= BASE_URL ?>/assets/js/metismenu.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/simplebar.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/waves.js"></script>
<script src="<?= BASE_URL ?>/assets/js/theme.js"></script>

<!-- DataTables Buttons (CSS mejor en <head> para evitar FOUC) -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">

<!-- Buttons + deps (IMPORTANTE: primero deps, luego buttons.html5) -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>


</body>

</html>