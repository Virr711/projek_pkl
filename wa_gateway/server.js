const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } = require('@whiskeysockets/baileys');
const express = require('express');
const QRCode = require('qrcode');
const cors = require('cors');
const pino = require('pino');
const path = require('path');
const fs = require('fs');

const app = express();
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

const PORT = 3000;
let sock = null;
let qrCodeData = null;
let isConnected = false;
let isConnecting = false;

const AUTH_DIR = path.join(__dirname, 'auth_info_baileys');

function cleanAuthDir() {
    try {
        if (fs.existsSync(AUTH_DIR)) {
            console.log('[LOCAL WA GATEWAY] 🧹 Membersihkan folder sesi lama (auth_info_baileys)...');
            fs.rmSync(AUTH_DIR, { recursive: true, force: true });
        }
    } catch (e) {
        console.error('[LOCAL WA GATEWAY ERROR] Gagal menghapus auth_info_baileys:', e.message);
    }
}

async function connectToWhatsApp() {
    if (isConnecting) return;
    isConnecting = true;

    try {
        const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
        const { version } = await fetchLatestBaileysVersion();

        sock = makeWASocket({
            version,
            auth: state,
            printQRInTerminal: false,
            logger: pino({ level: 'silent' }),
            browser: ['SIMAN-BPJ Gateway', 'Chrome', '1.0.0'],
            connectTimeoutMs: 60000,
            defaultQueryTimeoutMs: 60000,
            keepAliveIntervalMs: 25000,
        });

        sock.ev.on('creds.update', saveCreds);

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                try {
                    qrCodeData = await QRCode.toDataURL(qr);
                    isConnected = false;
                    
                    console.log('\n=======================================================================');
                    console.log('   SCAN QR CODE WHATSAPP DI BAWAH ATAU DI WEB UI ALISA (NOMOR BARU)');
                    console.log('=======================================================================');
                    QRCode.toString(qr, { type: 'terminal', small: true }, (err, url) => {
                        if (!err) console.log(url);
                    });
                    console.log('[LOCAL WA GATEWAY] 📲 QR Code Berhasil Dibuat! Silakan Scan QR Code di atas atau di Web UI.');
                    console.log('Web UI URL: http://localhost/bengkel_bpj/whatsapp_setting.php\n');
                } catch (e) {
                    console.error('[LOCAL WA GATEWAY ERROR] Gagal membuat QR Code Data URL:', e.message);
                }
            }

            if (connection === 'close') {
                isConnecting = false;
                const statusCode = (lastDisconnect?.error)?.output?.statusCode;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut && statusCode !== 401 && statusCode !== 403;
                
                console.log(`[LOCAL WA GATEWAY] Connection closed (Status Code: ${statusCode || 'unknown'}). Reconnecting: ${shouldReconnect}`);

                if (!shouldReconnect || statusCode === DisconnectReason.badSession || statusCode === 401) {
                    console.log('[LOCAL WA GATEWAY] Sesi tidak valid atau telah keluar (Logged Out). Resetting auth directory...');
                    cleanAuthDir();
                    qrCodeData = null;
                    isConnected = false;
                    setTimeout(connectToWhatsApp, 2000);
                } else {
                    setTimeout(connectToWhatsApp, 3000);
                }
            } else if (connection === 'open') {
                isConnecting = false;
                console.log('\n=======================================================================');
                console.log('   ✅ WHATSAPP BERHASIL TERHUBUNG! SYSTEM IS 100% UNLIMITED & READY.');
                console.log('=======================================================================\n');
                isConnected = true;
                qrCodeData = null;
            }
        });
    } catch (err) {
        isConnecting = false;
        console.error('[LOCAL WA GATEWAY CONNECT ERROR]', err.message);
        setTimeout(connectToWhatsApp, 3000);
    }
}

// 1. Status Check & QR Code Endpoint
app.get('/status', (req, res) => {
    res.json({
        connected: isConnected,
        qr: qrCodeData,
        message: isConnected ? 'WhatsApp Local Gateway Terhubung (UNLIMITED ACTIVE)' : 'Menunggu Scan QR Code'
    });
});

// 2. Send Message Endpoint (100% UNLIMITED FREE AUTOMATIC SENDING)
app.post('/send-message', async (req, res) => {
    try {
        const phone = req.body.phone || req.body.number;
        const message = req.body.message;

        if (!phone || !message) {
            return res.status(400).json({ success: false, message: 'Nomor telepon dan pesan wajib diisi.' });
        }

        if (!isConnected || !sock) {
            return res.status(503).json({ success: false, message: 'Local WA Gateway belum terhubung. Silakan scan QR Code.' });
        }

        let cleanPhone = phone.replace(/[^0-9]/g, '');
        if (cleanPhone.startsWith('0')) {
            cleanPhone = '62' + cleanPhone.substring(1);
        }

        let targetJid = cleanPhone.includes('@s.whatsapp.net') ? cleanPhone : `${cleanPhone}@s.whatsapp.net`;
        try {
            const [waCheck] = await sock.onWhatsApp(cleanPhone);
            if (waCheck && waCheck.exists && waCheck.jid) {
                targetJid = waCheck.jid;
            }
        } catch (e) {
            console.log('[LOCAL WA GATEWAY] onWhatsApp warning:', e.message);
        }

        await sock.sendMessage(targetJid, { text: message });

        console.log(`[LOCAL WA GATEWAY] ✅ Pesan otomatis terkirim ke ${cleanPhone} (${targetJid})`);
        return res.json({
            success: true,
            status: true,
            is_automatic: true,
            gateway: 'local_baileys',
            message: `Pesan WhatsApp BERHASIL terkirim OTOMATIS (Unlimited Local Gateway) ke ${cleanPhone}!`
        });
    } catch (error) {
        console.error('[LOCAL WA GATEWAY ERROR]', error);
        return res.status(500).json({ success: false, message: 'Gagal mengirim pesan: ' + error.message });
    }
});

// 3. Logout / Reset Session Endpoint (For Changing Sender Number)
app.post('/logout', async (req, res) => {
    try {
        console.log('[LOCAL WA GATEWAY] 🔄 Memproses Reset Sesi / Ganti Nomor WhatsApp...');
        isConnected = false;
        qrCodeData = null;

        if (sock) {
            try { sock.ev.removeAllListeners(); } catch(e) {}
            try { await sock.logout(); } catch(e) {}
            try { sock.end(); } catch(e) {}
            sock = null;
        }

        cleanAuthDir();

        setTimeout(connectToWhatsApp, 1500);

        return res.json({
            success: true,
            message: 'Sesi WhatsApp berhasil di-reset! Silakan scan QR Code baru di layar atau terminal.'
        });
    } catch (error) {
        console.error('[LOCAL WA GATEWAY LOGOUT ERROR]', error);
        cleanAuthDir();
        setTimeout(connectToWhatsApp, 1500);
        return res.json({
            success: true,
            message: 'Sesi WhatsApp berhasil dibersihkan! Silakan scan QR Code baru.'
        });
    }
});

// Start Server
app.listen(PORT, () => {
    console.log(`=======================================================`);
    console.log(`  🚀 LOCAL UNLIMITED WHATSAPP GATEWAY SERVING ON PORT ${PORT}`);
    console.log(`  URL Status: http://localhost:${PORT}/status`);
    console.log(`  SIMAN-BPJ Balai Pengelolaan Jalan Wilayah Tegal 2026`);
    console.log(`=======================================================`);
    connectToWhatsApp();
});
