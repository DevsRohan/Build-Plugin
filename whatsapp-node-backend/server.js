/**
 * WhatsApp Order Notifier - Node.js Backend
 * 
 * Deploy this on Hugging Face Spaces (Docker) for FREE WhatsApp messaging.
 * No paid APIs needed. Uses whatsapp-web.js to connect via QR code scan.
 * 
 * Endpoints:
 *   GET  /status    - Check WhatsApp connection status
 *   GET  /qr       - Get QR code for authentication (base64 image)
 *   POST /send     - Send a WhatsApp message
 *   POST /logout   - Disconnect WhatsApp session
 *   GET  /health   - Health check
 */

const express = require('express');
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const cors = require('cors');
const helmet = require('helmet');
const morgan = require('morgan');
const path = require('path');
const fs = require('fs');

// ============================================
// Configuration
// ============================================
const PORT = process.env.PORT || 7860; // HF Spaces uses 7860
const API_SECRET = process.env.API_SECRET || ''; // Optional: secure your endpoint
const SESSION_DIR = path.join(__dirname, '.wwebjs_auth');

// Ensure session directory exists
if (!fs.existsSync(SESSION_DIR)) {
    fs.mkdirSync(SESSION_DIR, { recursive: true });
}

// ============================================
// Express App Setup
// ============================================
const app = express();

app.use(helmet({ contentSecurityPolicy: false }));
app.use(cors());
app.use(express.json());
app.use(morgan('combined'));

// ============================================
// WhatsApp Client State
// ============================================
let clientState = {
    status: 'initializing', // initializing, qr_ready, authenticated, ready, disconnected
    qrCode: null,           // Base64 QR code image
    qrText: null,           // Raw QR text
    info: null,             // Connected account info
    lastError: null,
    messagesSent: 0,
    startedAt: new Date().toISOString()
};

// ============================================
// WhatsApp Client Initialization
// ============================================
const client = new Client({
    authStrategy: new LocalAuth({
        dataPath: SESSION_DIR
    }),
    puppeteer: {
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--single-process',
            '--disable-gpu'
        ]
    }
});

// QR Code event - fires when QR needs to be scanned
client.on('qr', async (qr) => {
    console.log('📱 QR Code generated. Scan with WhatsApp to authenticate.');
    clientState.status = 'qr_ready';
    clientState.qrText = qr;
    
    try {
        // Generate QR as base64 PNG image
        clientState.qrCode = await qrcode.toDataURL(qr, {
            width: 300,
            margin: 2,
            color: { dark: '#128C7E', light: '#FFFFFF' }
        });
    } catch (err) {
        console.error('QR generation error:', err);
    }
});

// Authentication event
client.on('authenticated', () => {
    console.log('✅ WhatsApp authenticated successfully!');
    clientState.status = 'authenticated';
    clientState.qrCode = null;
    clientState.qrText = null;
});

// Ready event - client is fully connected
client.on('ready', () => {
    console.log('🟢 WhatsApp client is READY!');
    clientState.status = 'ready';
    clientState.qrCode = null;
    clientState.qrText = null;
    clientState.lastError = null;
    
    // Get connected account info
    if (client.info) {
        clientState.info = {
            pushname: client.info.pushname,
            phone: client.info.wid.user,
            platform: client.info.platform
        };
        console.log(`📞 Connected as: ${client.info.pushname} (${client.info.wid.user})`);
    }
});

// Disconnected event
client.on('disconnected', (reason) => {
    console.log('🔴 WhatsApp disconnected:', reason);
    clientState.status = 'disconnected';
    clientState.info = null;
    clientState.lastError = reason;
    
    // Auto-reinitialize after disconnect
    setTimeout(() => {
        console.log('♻️ Attempting to reconnect...');
        client.initialize().catch(err => {
            console.error('Reconnection failed:', err.message);
        });
    }, 5000);
});

// Auth failure
client.on('auth_failure', (msg) => {
    console.error('❌ Authentication failed:', msg);
    clientState.status = 'disconnected';
    clientState.lastError = 'Authentication failed: ' + msg;
});

// ============================================
// Middleware: API Secret Validation
// ============================================
function validateSecret(req, res, next) {
    // If no API_SECRET is set, skip validation (open mode)
    if (!API_SECRET) {
        return next();
    }
    
    const provided = req.headers['x-api-secret'] || 
                     req.headers['authorization']?.replace('Bearer ', '') ||
                     req.query.secret;
    
    if (provided !== API_SECRET) {
        return res.status(401).json({
            success: false,
            error: 'Invalid or missing API secret. Set x-api-secret header.'
        });
    }
    
    next();
}

// Apply to all routes except health
app.use('/send', validateSecret);
app.use('/logout', validateSecret);

// ============================================
// API Routes
// ============================================

/**
 * GET /health - Simple health check
 */
app.get('/health', (req, res) => {
    res.json({
        success: true,
        service: 'WhatsApp Order Notifier Backend',
        version: '1.0.0',
        uptime: process.uptime(),
        timestamp: new Date().toISOString()
    });
});

/**
 * GET /status - Get WhatsApp connection status
 */
app.get('/status', (req, res) => {
    res.json({
        success: true,
        status: clientState.status,
        connected: clientState.status === 'ready',
        info: clientState.info,
        messagesSent: clientState.messagesSent,
        startedAt: clientState.startedAt,
        lastError: clientState.lastError
    });
});

/**
 * GET /qr - Get QR code for authentication
 * Returns base64 encoded PNG image of QR code
 */
app.get('/qr', (req, res) => {
    if (clientState.status === 'ready') {
        return res.json({
            success: true,
            status: 'already_connected',
            message: 'WhatsApp is already connected. No QR needed.',
            info: clientState.info
        });
    }
    
    if (!clientState.qrCode) {
        return res.json({
            success: false,
            status: clientState.status,
            message: 'QR code not yet generated. Please wait and try again in a few seconds.',
            qr: null
        });
    }
    
    res.json({
        success: true,
        status: 'qr_ready',
        message: 'Scan this QR code with your WhatsApp app.',
        qr: clientState.qrCode
    });
});

/**
 * POST /send - Send a WhatsApp message
 * 
 * Body: { phone: "919876543210", message: "Hello!" }
 * Phone should include country code without + sign
 */
app.post('/send', async (req, res) => {
    const { phone, message } = req.body;
    
    // Validation
    if (!phone || !message) {
        return res.status(400).json({
            success: false,
            error: 'Both "phone" and "message" fields are required.'
        });
    }
    
    // Check if client is ready
    if (clientState.status !== 'ready') {
        return res.status(503).json({
            success: false,
            error: `WhatsApp is not connected. Current status: ${clientState.status}`,
            status: clientState.status
        });
    }
    
    try {
        // Format phone number: remove +, spaces, dashes
        let formattedPhone = phone.toString().replace(/[^0-9]/g, '');
        
        // Remove leading 0 if present (Indian numbers sometimes written as 09876...)
        if (formattedPhone.startsWith('0')) {
            formattedPhone = formattedPhone.substring(1);
        }
        
        // Ensure it has country code (if less than 11 digits, assume Indian +91)
        if (formattedPhone.length === 10) {
            formattedPhone = '91' + formattedPhone;
        }
        
        // WhatsApp chat ID format
        const chatId = formattedPhone + '@c.us';
        
        // Check if number is registered on WhatsApp
        const isRegistered = await client.isRegisteredUser(chatId);
        if (!isRegistered) {
            return res.status(400).json({
                success: false,
                error: `Phone number ${phone} is not registered on WhatsApp.`
            });
        }
        
        // Send message
        const result = await client.sendMessage(chatId, message);
        
        clientState.messagesSent++;
        
        console.log(`✅ Message sent to ${formattedPhone} (ID: ${result.id.id})`);
        
        res.json({
            success: true,
            messageId: result.id.id,
            timestamp: result.timestamp,
            to: formattedPhone,
            status: 'sent'
        });
        
    } catch (error) {
        console.error(`❌ Send failed to ${phone}:`, error.message);
        
        res.status(500).json({
            success: false,
            error: error.message || 'Failed to send message'
        });
    }
});

/**
 * POST /send-bulk - Send message to multiple numbers
 * 
 * Body: { phones: ["919876543210", "919876543211"], message: "Hello!" }
 */
app.post('/send-bulk', validateSecret, async (req, res) => {
    const { phones, message } = req.body;
    
    if (!phones || !Array.isArray(phones) || !message) {
        return res.status(400).json({
            success: false,
            error: '"phones" (array) and "message" are required.'
        });
    }
    
    if (clientState.status !== 'ready') {
        return res.status(503).json({
            success: false,
            error: `WhatsApp is not connected. Status: ${clientState.status}`
        });
    }
    
    const results = [];
    
    for (const phone of phones.slice(0, 20)) { // Max 20 at a time
        try {
            let formattedPhone = phone.toString().replace(/[^0-9]/g, '');
            if (formattedPhone.startsWith('0')) formattedPhone = formattedPhone.substring(1);
            if (formattedPhone.length === 10) formattedPhone = '91' + formattedPhone;
            
            const chatId = formattedPhone + '@c.us';
            const result = await client.sendMessage(chatId, message);
            
            clientState.messagesSent++;
            results.push({ phone: formattedPhone, success: true, messageId: result.id.id });
            
            // Small delay between messages to avoid rate limiting
            await new Promise(resolve => setTimeout(resolve, 1000));
            
        } catch (error) {
            results.push({ phone, success: false, error: error.message });
        }
    }
    
    res.json({
        success: true,
        total: phones.length,
        sent: results.filter(r => r.success).length,
        failed: results.filter(r => !r.success).length,
        results
    });
});

/**
 * POST /logout - Disconnect WhatsApp session
 */
app.post('/logout', async (req, res) => {
    try {
        await client.logout();
        clientState.status = 'disconnected';
        clientState.info = null;
        clientState.qrCode = null;
        
        res.json({
            success: true,
            message: 'WhatsApp session disconnected. You will need to scan QR again.'
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

/**
 * GET / - Landing page with status info
 */
app.get('/', (req, res) => {
    const statusEmoji = {
        'initializing': '⏳',
        'qr_ready': '📱',
        'authenticated': '🔑',
        'ready': '🟢',
        'disconnected': '🔴'
    };
    
    res.send(`
    <!DOCTYPE html>
    <html>
    <head>
        <title>WhatsApp Order Notifier</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0F172A; color: #F1F5F9; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .container { max-width: 500px; width: 90%; padding: 40px; text-align: center; }
            .logo { width: 64px; height: 64px; background: linear-gradient(135deg, #25D366, #128C7E); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 32px; }
            h1 { font-size: 24px; margin-bottom: 8px; }
            .subtitle { color: #94A3B8; margin-bottom: 32px; }
            .status-card { background: #1E293B; border: 1px solid #334155; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
            .status-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #334155; }
            .status-row:last-child { border-bottom: none; }
            .status-label { color: #94A3B8; font-size: 14px; }
            .status-value { font-weight: 600; font-size: 14px; }
            .badge { padding: 4px 12px; border-radius: 100px; font-size: 12px; font-weight: 600; }
            .badge-green { background: rgba(37, 211, 102, 0.15); color: #25D366; }
            .badge-yellow { background: rgba(245, 158, 11, 0.15); color: #F59E0B; }
            .badge-red { background: rgba(239, 68, 68, 0.15); color: #EF4444; }
            .qr-section { margin-top: 24px; }
            .qr-section img { border-radius: 12px; background: white; padding: 16px; }
            .qr-text { color: #94A3B8; font-size: 13px; margin-top: 12px; }
            .endpoints { text-align: left; background: #1E293B; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-top: 24px; }
            .endpoints h3 { font-size: 14px; margin-bottom: 12px; color: #25D366; }
            .endpoint { font-family: monospace; font-size: 12px; padding: 6px 0; color: #94A3B8; border-bottom: 1px solid #334155; }
            .endpoint:last-child { border-bottom: none; }
            .method { color: #25D366; font-weight: 700; }
            .refresh-btn { margin-top: 16px; background: linear-gradient(135deg, #25D366, #128C7E); color: white; border: none; padding: 10px 24px; border-radius: 8px; font-size: 14px; cursor: pointer; font-weight: 500; }
            .refresh-btn:hover { opacity: 0.9; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo">💬</div>
            <h1>WhatsApp Order Notifier</h1>
            <p class="subtitle">Self-hosted WhatsApp messaging backend</p>
            
            <div class="status-card">
                <div class="status-row">
                    <span class="status-label">Status</span>
                    <span class="badge ${clientState.status === 'ready' ? 'badge-green' : clientState.status === 'qr_ready' ? 'badge-yellow' : 'badge-red'}">
                        ${statusEmoji[clientState.status] || '❓'} ${clientState.status.replace('_', ' ').toUpperCase()}
                    </span>
                </div>
                <div class="status-row">
                    <span class="status-label">Account</span>
                    <span class="status-value">${clientState.info ? clientState.info.pushname + ' (' + clientState.info.phone + ')' : 'Not connected'}</span>
                </div>
                <div class="status-row">
                    <span class="status-label">Messages Sent</span>
                    <span class="status-value">${clientState.messagesSent}</span>
                </div>
                <div class="status-row">
                    <span class="status-label">Uptime</span>
                    <span class="status-value">${Math.floor(process.uptime() / 60)} min</span>
                </div>
            </div>
            
            ${clientState.status === 'qr_ready' && clientState.qrCode ? `
            <div class="qr-section">
                <img src="${clientState.qrCode}" alt="Scan QR Code" width="250" />
                <p class="qr-text">Open WhatsApp → Settings → Linked Devices → Scan this QR</p>
            </div>
            ` : ''}
            
            <button class="refresh-btn" onclick="location.reload()">🔄 Refresh Status</button>
            
            <div class="endpoints">
                <h3>API Endpoints</h3>
                <div class="endpoint"><span class="method">GET</span>  /status - Connection status</div>
                <div class="endpoint"><span class="method">GET</span>  /qr - Get QR code</div>
                <div class="endpoint"><span class="method">POST</span> /send - Send message {phone, message}</div>
                <div class="endpoint"><span class="method">POST</span> /send-bulk - Bulk send {phones[], message}</div>
                <div class="endpoint"><span class="method">POST</span> /logout - Disconnect session</div>
                <div class="endpoint"><span class="method">GET</span>  /health - Health check</div>
            </div>
        </div>
    </body>
    </html>
    `);
});

// ============================================
// Start Server
// ============================================
app.listen(PORT, '0.0.0.0', () => {
    console.log(`
╔══════════════════════════════════════════════════════╗
║   WhatsApp Order Notifier - Backend Server          ║
║   Running on port ${PORT}                              ║
║                                                      ║
║   Endpoints:                                         ║
║   GET  /status  - Check connection                   ║
║   GET  /qr      - Get QR code                        ║
║   POST /send    - Send message                       ║
║   POST /logout  - Disconnect                         ║
╚══════════════════════════════════════════════════════╝
    `);
    
    // Initialize WhatsApp client
    console.log('⏳ Initializing WhatsApp client...');
    client.initialize().catch(err => {
        console.error('❌ Failed to initialize WhatsApp client:', err.message);
        clientState.status = 'disconnected';
        clientState.lastError = err.message;
    });
});
