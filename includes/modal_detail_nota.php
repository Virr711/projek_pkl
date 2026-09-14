<!-- Modal Rincian Detail Nota & Subtotal Item -->
<div class="modal fade" id="modalDetailNota" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 bg-dark text-white">
            <div class="modal-header text-white border-secondary" style="background-color: #0284c7 !important;">
                <h5 class="modal-title fw-bold" id="detailNotaTitle"><i class="fa-solid fa-receipt me-2"></i> Rincian Subtotal Nota Servis</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4 p-3 rounded-3 border border-secondary modal-info-box">
                    <div class="col-md-6">
                        <span class="text-white-50 small d-block">Armada Kendaraan / Peralatan:</span>
                        <h6 class="fw-extrabold text-white mb-1" id="d_nama_armada">-</h6>
                        <span class="fw-bold text-white font-monospace fs-6" id="d_kode_plat">-</span>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="text-white-50 small d-block">Tanggal Pelaksanaan Servis:</span>
                        <h6 class="fw-bold text-info mb-1" id="d_tgl_servis">-</h6>
                        <span class="badge bg-primary" style="background-color: #0284c7 !important;" id="d_jenis_servis">-</span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-white-50 small d-block">Bengkel / Tempat Servis:</span>
                        <strong class="text-white" id="d_nama_bengkel">-</strong>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="text-white-50 small d-block">Nama Layanan:</span>
                        <strong class="text-white" id="d_nama_layanan">-</strong>
                    </div>
                </div>

                <h6 class="fw-bold text-white mb-2"><i class="fa-solid fa-list-check text-info me-2"></i> Break-Down Sparepart & Subtotal Per Item:</h6>
                <div class="p-3 border border-secondary rounded-3 mb-4 modal-info-box">
                    <div id="d_rincian_subtotal_list" class="lh-lg font-monospace text-white" style="white-space: pre-line;">
                        -
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 border border-info modal-summary-box">
                    <span class="fw-bold text-white">GRAND TOTAL BIAYA SERVIS:</span>
                    <h4 class="fw-extrabold text-info m-0" id="d_grand_total">Rp 0</h4>
                </div>

                <div id="d_foto_container" class="mt-4 text-center d-none border-top border-secondary pt-3">
                    <h6 class="fw-bold text-white mb-2"><i class="fa-solid fa-image text-info me-2"></i> Foto Bukti Nota Fisik:</h6>
                    <img id="d_foto_img" src="" alt="Foto Nota" class="img-fluid rounded border border-secondary shadow-sm" style="max-height: 350px;">
                </div>
            </div>
            <div class="modal-footer border-secondary d-flex justify-content-between align-items-center">
                <div>
                    <?php if ($current_role === 'admin' || $current_role === 'pimpinan'): ?>
                        <a id="d_btn_cetak_nota" href="#" target="_blank" class="btn btn-primary rounded-pill px-4 shadow fw-bold me-2" style="background-color: #0284c7 !important; border: none;">
                            <i class="fa-solid fa-print me-2"></i> Cetak Nota Servis (Print PDF)
                        </a>
                    <?php endif; ?>
                    <?php if ($current_role === 'admin' || $current_role === 'teknisi'): ?>
                        <a id="d_btn_edit_nota" href="#" class="btn btn-warning text-dark rounded-pill px-3 shadow fw-bold">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Servis
                        </a>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function showDetailNotaModal(log) {
    document.getElementById('d_nama_armada').innerText = log.nama + ' (' + (log.merk || '-') + ')';
    document.getElementById('d_kode_plat').innerText = log.kode_plat;
    document.getElementById('d_tgl_servis').innerText = log.tgl_servis;
    document.getElementById('d_jenis_servis').innerText = log.jenis_servis;
    document.getElementById('d_nama_bengkel').innerText = log.nama_bengkel || '-';
    document.getElementById('d_nama_layanan').innerText = log.nama_layanan || '-';
    
    // Set Print & Edit Links
    var btnCetak = document.getElementById('d_btn_cetak_nota');
    if (btnCetak) btnCetak.href = 'cetak_nota_servis.php?id=' + log.id + '&autoprint=1';

    var btnEdit = document.getElementById('d_btn_edit_nota');
    if (btnEdit) btnEdit.href = 'servis_form.php?edit_id=' + log.id;

    // Format Rincian Subtotal Item
    document.getElementById('d_rincian_subtotal_list').innerText = log.rincian_item || 'Belum ada rincian subtotal sparepart.';
    
    // Format Currency
    let totalFormatted = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(log.total_biaya);
    document.getElementById('d_grand_total').innerText = totalFormatted;

    // Foto Nota Container
    let fotoBox = document.getElementById('d_foto_container');
    let fotoImg = document.getElementById('d_foto_img');
    if (log.foto_nota) {
        fotoImg.src = 'uploads/nota/' + log.foto_nota;
        fotoBox.classList.remove('d-none');
    } else {
        fotoBox.classList.add('d-none');
    }

    var modal = new bootstrap.Modal(document.getElementById('modalDetailNota'));
    modal.show();
}
</script>
