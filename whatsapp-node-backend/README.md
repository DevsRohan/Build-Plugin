---
title: WhatsApp Order Notifier
emoji: 💬
colorFrom: green
colorTo: blue
sdk: docker
app_port: 7860
pinned: false
---

# WhatsApp Order Notifier - Node.js Backend

Free WhatsApp messaging backend for WooCommerce. Deploy on **Hugging Face Spaces** and get unlimited WhatsApp notifications without paying for any API.

## How It Works

1. Deploy this code on Hugging Face Spaces (Docker)
2. Open the Space URL and scan the QR code with your WhatsApp
3. Copy the Space URL into your WordPress plugin settings
4. Done! All WooCommerce notifications now go to your WhatsApp for FREE

## Deploy on Hugging Face Spaces

### Step 1: Create a Space

1. Go to [huggingface.co/spaces](https://huggingface.co/spaces)
2. Click **"Create new Space"**
3. Choose **Docker** as the SDK
4. Set visibility to **Private** (recommended)
5. Name it something like `whatsapp-notifier`

### Step 2: Upload Files

Upload these files to your Space:
- `server.js`
- `package.json`
- `Dockerfile`

### Step 3: Set Environment Variables (Optional)

In Space Settings → Variables:
- `API_SECRET` = any random string (adds security to your /send endpoint)

### Step 4: Scan QR Code

1. Wait for the Space to build (1-2 minutes)
2. Open the Space URL
3. You'll see a QR code
4. Open WhatsApp on your phone → Settings → Linked Devices → Link a Device
5. Scan the QR code
6. Done! Status should show "READY"

### Step 5: Enter URL in WordPress Plugin

Copy your Space URL (e.g., `https://your-username-whatsapp-notifier.hf.space`) and paste it in the WordPress plugin settings.

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | Dashboard with status and QR |
| GET | `/health` | Health check |
| GET | `/status` | Connection status |
| GET | `/qr` | Get QR code (base64) |
| POST | `/send` | Send message |
| POST | `/send-bulk` | Send to multiple numbers |
| POST | `/logout` | Disconnect session |

### Send a Message

```bash
curl -X POST https://your-space.hf.space/send \
  -H "Content-Type: application/json" \
  -H "x-api-secret: YOUR_SECRET" \
  -d '{"phone": "919876543210", "message": "Hello from WooCommerce!"}'
```

### Check Status

```bash
curl https://your-space.hf.space/status
```

## Phone Number Format

- Include country code WITHOUT the + sign
- Example: `919876543210` (India)
- Example: `628123456789` (Indonesia)
- If you pass 10 digits, it auto-adds `91` (India) prefix

## Security

- Set `API_SECRET` environment variable to protect the /send endpoint
- Keep your Space **private** on Hugging Face
- The session persists so you don't need to scan QR again after restart

## Troubleshooting

| Issue | Solution |
|-------|----------|
| QR not showing | Wait 10-15 seconds, refresh the page |
| Disconnected | Open the Space URL, scan QR again |
| Message not sending | Check if recipient has WhatsApp |
| Space sleeping | Hugging Face free tier sleeps after 48h inactivity. Upgrade to keep alive. |

## Important Notes

- Free Hugging Face Spaces may sleep after 48 hours of inactivity
- For 24/7 uptime, use a paid HF Space ($5/month) or any VPS
- Session persists across restarts (stored in `.wwebjs_auth` folder)
- Rate limit yourself: don't send more than 1 message per second
