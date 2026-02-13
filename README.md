<div align="center">

# ROOMs
### Self-hosted chat platform (PHP + JSON storage)

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=for-the-badge&logo=php&logoColor=white)
![PWA](https://img.shields.io/badge/PWA-enabled-0f766e?style=for-the-badge)
![Storage](https://img.shields.io/badge/Storage-JSON%20Files-0a7ea4?style=for-the-badge)
![License](https://img.shields.io/badge/License-Private-181717?style=for-the-badge)

</div>

ROOMs is a secure, self-hosted chat application with group rooms, direct messaging,
file sharing, admin tools, and installable PWA support.

## Repository Layout

```text
ROOMs/
  README.md
  rooms/
    index.html
    app.php
    chat.php
    install.php
    api/
    admin/
    data/
    uploads/
```

## Core Features

- User registration and login with bcrypt-hashed passwords
- Public, private, and secret rooms with invite-code flow
- Direct messages with contact management
- File and media sharing in chats
- Admin dashboard for users, rooms, settings, and logs
- Rate limiting and session-based auth controls
- PWA support (`manifest.json` + `sw.js`)

## Requirements

- PHP `7.4+`
- Apache with `mod_rewrite` (or equivalent nginx config)
- Writable app directories:
  - `rooms/data/`
  - `rooms/uploads/`
  - `rooms/cache/`
  - `rooms/chats/`
  - `rooms/status/`

## Quick Setup

```bash
git clone https://github.com/30200dotIR/ROOMs.git
cd ROOMs/rooms
```

1. Upload or serve the `rooms/` directory from your web root.
2. Open `install.php` once to create required folders and seed files.
3. Log in to admin at `/admin/login.php` (default password: `admin123`).
4. Change the admin password immediately in `Admin -> Settings`.
5. Delete `install.php` after installation.

## API Modules

- `rooms/api/auth.php` - registration, login, profile, password updates
- `rooms/api/messages.php` - send/fetch/delete/react/typing operations
- `rooms/api/rooms.php` - room creation, listing, join/leave, updates
- `rooms/api/contacts.php` - contact search, add/remove, block/unblock, P2P
- `rooms/api/admin.php` - admin-only actions and system controls

## Security Notes

- Passwords use bcrypt (`cost=12`)
- Session cookies are `HttpOnly` + `SameSite=Lax`
- Rate limiting is enabled for key actions
- Upload handling includes extension validation
- `.htaccess` applies hardening and access protections

## Contributing

1. Create a branch from `main`.
2. Make your changes in focused commits.
3. Open a pull request with a clear summary.

## License

No license file is currently defined. Add `LICENSE` if you want reuse terms.
