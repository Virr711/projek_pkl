    </div> <!-- End Main Content -->

    <!-- Modal Riwayat Perubahan Nopol Global -->
    <div class="modal fade" id="modalRiwayatPlatGlobal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4 border-secondary bg-dark text-white shadow-lg">
                <div class="modal-header border-secondary bg-info bg-gradient">
                    <h5 class="modal-title fw-bold text-white mb-0">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i> Riwayat Perubahan Nopol (Kode Plat)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="containerRiwayatPlatBody">
                        <div class="text-center py-4 text-white-50">
                            <i class="fa-solid fa-spinner fa-spin fs-3 mb-2"></i>
                            <p class="mb-0">Memuat riwayat perubahan Nopol...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Copyright Bar -->
    <footer class="text-center small alisa-footer mt-auto">
        <div class="container-fluid">
            &copy; 2026 <strong>ALISA</strong> &bull; Developed by <strong>POLITEKNIK PURBAYA (Zidni, Zaidan, dan Virgi)</strong> for Balai Pengelolaan Jalan Wilayah Tegal.
        </div>
    </footer>

    <!-- jQuery & Select2 JS Bundle -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function() {
        if ($.fn.select2) {
            $('.select2-searchable').select2({
                theme: 'bootstrap-5',
                placeholder: '-- Ketik Nopol / Nama Unit Armada --',
                allowClear: true,
                width: '100%'
            });
            $('.select2-searchable').on('change', function() {
                if (this.form) {
                    this.form.submit();
                }
            });
        }
    });

    function viewRiwayatPlat(idKendaraan, namaUnit) {
        var modalEl = document.getElementById('modalRiwayatPlatGlobal');
        var modal = new bootstrap.Modal(modalEl);
        var container = document.getElementById('containerRiwayatPlatBody');
        
        container.innerHTML = '<div class="text-center py-4 text-white-50"><i class="fa-solid fa-spinner fa-spin fs-3 mb-2"></i><p class="mb-0">Memuat riwayat perubahan Nopol unit <strong>' + (namaUnit || '') + '</strong>...</p></div>';
        modal.show();
        
        fetch('ajax_get_riwayat_plat.php?id=' + idKendaraan)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.data.length > 0) {
                    var html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr class="text-info small border-bottom border-secondary"><th>No</th><th>Nopol Lama</th><th>Nopol Baru</th><th>Tanggal Perubahan</th><th>Keterangan / Alasan</th><th>Petugas</th></tr></thead><tbody>';
                    data.data.forEach((item, index) => {
                        html += '<tr>';
                        html += '<td class="text-white-50">' + (index + 1) + '</td>';
                        html += '<td class="fw-bold text-danger font-monospace"><span class="badge bg-danger bg-opacity-25 text-danger px-2.5 py-1 rounded-pill">' + item.plat_lama + '</span></td>';
                        html += '<td class="fw-bold text-success font-monospace"><span class="badge bg-success bg-opacity-25 text-success px-2.5 py-1 rounded-pill">' + item.plat_baru + '</span></td>';
                        html += '<td class="small text-white-50">' + item.tgl_perubahan_formatted + '</td>';
                        html += '<td class="text-white small">' + (item.keterangan || '-') + '</td>';
                        html += '<td><span class="badge bg-secondary rounded-pill">' + (item.diubah_oleh || 'Admin') + '</span></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="text-center py-4 text-white-50"><i class="fa-solid fa-folder-open fs-2 mb-2 text-warning"></i><p class="mb-0">Belum ada riwayat perubahan Nopol (plat) untuk unit ini.</p></div>';
                }
            })
            .catch(err => {
                container.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat data riwayat Nopol.</div>';
            });
    }
    </script>
</body>
</html>
