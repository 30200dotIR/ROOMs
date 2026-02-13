# ROOMs Chat Application

A secure, self-hosted chat application built with PHP and JSON file storage. Zero external dependencies. Works on any shared hosting with PHP 7.4+.

## Features

- **User Authentication** – Registration, login, sessions with bcrypt password hashing
- **Group Rooms** – Public, private, and secret rooms with invite codes
- **Direct Messages** – P2P chat between users with contact management
- **File Sharing** – Image, document, and file uploads in chat
- **Real-time Updates** – Polling-based message delivery with typing indicators
- **Online Presence** – See who's online in rooms and direct chats
- **Admin Panel** – User management, room oversight, system settings, activity logs
- **Rate Limiting** – Configurable per-action rate limits for security
- **PWA Support** – Installable as a mobile app with offline caching
- **Dark Theme** – WhatsApp-inspired dark UI, fully responsive

## Requirements

- PHP 7.4 or higher
- Apache with mod_rewrite (or nginx equivalent)
- Write permissions on `data/`, `uploads/`, `chats/`, `status/`, `cache/` directories

## Installation

1. **Upload** all files to your web server document root (or subdirectory)

2. **Set permissions** on writable directories:
   ```bash
   chmod -R 775 data/ uploads/ chats/ status/ cache/
   ```

3. **Create required subdirectories** (if not present):
   ```bash
   mkdir -p data/{users,rooms,messages,sessions,contacts,reports,admin/logs,rate_limits}
   mkdir -p uploads/{profiles,files,voice,temp}
   mkdir -p chats status cache
   ```

4. **Access the app** at `https://yourdomain.com/` (or wherever you uploaded it)

5. **Admin panel** is at `/admin/login.php`
   - Default password: `admin123`
   - **Change this immediately** after first login via Settings

## File Structure

```
├── index.html          # Landing page with login/register
├── app.php             # Dashboard / chat list
├── chat.php            # Chat interface (rooms & P2P)
├── manifest.json       # PWA manifest
├── sw.js               # Service worker
├── .htaccess           # Security & caching config
├── api/
│   ├── utils.php       # Core utilities, auth helpers
│   ├── auth.php        # Authentication endpoints
│   ├── messages.php    # Messaging operations
│   ├── rooms.php       # Room management
│   ├── contacts.php    # Contacts & P2P setup
│   └── admin.php       # Admin API endpoints
├── admin/
│   ├── login.php       # Admin login
│   ├── index.php       # Admin dashboard
│   ├── users.php       # User management
│   ├── rooms.php       # Room management
│   ├── settings.php    # System settings
│   └── logs.php        # Activity log viewer
├── data/               # JSON data storage (auto-created)
│   ├── users/          # User profiles
│   ├── rooms/          # Room metadata
│   ├── messages/       # Chat messages
│   ├── sessions/       # Active sessions
│   ├── rate_limits/    # Rate limit tracking
│   └── admin/          # Admin settings & logs
└── uploads/            # User file uploads
    ├── profiles/       # Avatar images
    ├── files/          # Shared files
    └── voice/          # Voice messages
```

## API Endpoints

All API endpoints accept GET or POST and return JSON.

### Authentication (`api/auth.php`)
| Action | Method | Parameters |
|--------|--------|------------|
| `register` | POST | username, password, displayName |
| `login` | POST | username, password |
| `logout` | GET | – |
| `check` | GET | – |
| `updateProfile` | POST | displayName, bio, avatar (file) |
| `changePassword` | POST | currentPassword, newPassword |

### Messages (`api/messages.php`)
| Action | Method | Parameters |
|--------|--------|------------|
| `send` | POST | chatId, text, file, replyTo |
| `fetch` | GET | chatId, since, limit |
| `typing` | GET | chatId |
| `delete` | POST | chatId, messageId |
| `react` | POST | chatId, messageId, emoji |

### Rooms (`api/rooms.php`)
| Action | Method | Parameters |
|--------|--------|------------|
| `create` | POST | name, description, type |
| `list` | GET | type (all/my/public), search |
| `get` | GET | roomId |
| `join` | POST | roomId |
| `joinByCode` | POST | code |
| `leave` | POST | roomId |
| `update` | POST | roomId, name, description, type |

### Contacts (`api/contacts.php`)
| Action | Method | Parameters |
|--------|--------|------------|
| `search` | GET | q |
| `add` / `remove` | POST | userId |
| `block` / `unblock` | POST | userId |
| `startP2P` | POST | userId |
| `listP2P` | GET | – |

## Security

- Passwords hashed with bcrypt (cost 12)
- Session-based auth with httponly, SameSite cookies
- Rate limiting on login, registration, messaging, and API calls
- Input sanitization on all user data
- File upload validation with extension whitelist
- Directory traversal protection via .htaccess
- Security headers (X-Content-Type-Options, X-Frame-Options, etc.)

## Configuration

Admin settings can be changed at `/admin/settings.php`:
- Toggle registration, room creation, P2P messaging
- Set max file upload size
- Set max room members
- Configure rate limits
- Change admin password

## License

This project is provided as-is for personal and educational use.
