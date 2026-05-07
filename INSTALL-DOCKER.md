# OpenBiblio Installation Guide — Docker

This guide will help you install and run OpenBiblio using Docker.

For traditional standalone installation (without Docker), see [INSTALL.md](INSTALL.md).

## Prerequisites

- Docker and Docker Compose installed on your server
- Basic command line knowledge
- (Optional) A domain name pointing to your server

## Quick Start

### 1. Create Installation Directory

Create a directory for your OpenBiblio installation:

```bash
mkdir openbiblio
cd openbiblio
```

### 2. Download Configuration Files

Download the required configuration files:

```bash
# Download docker-compose.prod.yml
wget https://raw.githubusercontent.com/your-org/openbiblio/main/docker-compose.prod.yml

# Download nginx configuration
wget https://raw.githubusercontent.com/your-org/openbiblio/main/nginx.conf

# Download environment template
wget https://raw.githubusercontent.com/your-org/openbiblio/main/.env.example
```

### 3. Configure Your Installation

Copy the example configuration file:

```bash
cp .env.example .env
```

Edit `.env` and set your preferences:

```bash
nano .env
```

**Required settings:**

```env
# Change these passwords!
DB_PASSWORD=your_secure_database_password
MYSQL_ROOT_PASSWORD=your_secure_root_password

# Generate secure keys (see below)
OBIB_UPGRADE_KEY=your_random_key_here
OBIB_PWD_FORGOTTEN_KEY=another_random_key_here
```

**Generate secure keys:**

```bash
openssl rand -base64 32
```

Run this command twice to generate two different keys for `OBIB_UPGRADE_KEY` and `OBIB_PWD_FORGOTTEN_KEY`.

Also set the image version you want to use:

```env
# Use a specific version (recommended for production)
VERSION=1.0.0
# Or use 'latest' for the most recent version
VERSION=latest
```

**Optional settings:**

```env
# Your timezone (e.g., Europe/Berlin, America/New_York)
TZ=Europe/Berlin

# Language: de (German) or en (English)
DB_LOCALE=en

# Load sample data for testing (yes/no)
INSTALL_TEST_DATA=no

# Set an admin password for automatic installation
# Leave blank to use the web-based installation wizard
INITIAL_ADMIN_PASSWORD=
```

### 4. Start OpenBiblio

Docker will automatically pull the OpenBiblio image from the registry:

```bash
docker compose -f docker-compose.prod.yml up -d
```

Wait about 30 seconds for all services to download and start.

### 5. Complete the Installation

**If you set `INITIAL_ADMIN_PASSWORD`:**

The installation runs automatically. Open your browser to:

```
http://your-server-address
```

Log in with:
- Username: **admin**
- Password: (the password you set in `INITIAL_ADMIN_PASSWORD`)

**If you left `INITIAL_ADMIN_PASSWORD` blank:**

Open the installation wizard in your browser:

```
http://your-server-address/install/index.php
```

Follow the on-screen instructions to:
1. Choose your language
2. Create the database
3. Set your admin password
4. (Optional) Load sample data

## Using OpenBiblio

Once installed, you can:

- **Log in** at the main page with username `admin` and your chosen password
- **Add library members** in the Admin section
- **Catalog items** in the Cataloging section
- **Check out and return items** in the Circulation section
- **Search the catalog** in the OPAC (public catalog)

## Stopping and Starting

**Stop OpenBiblio:**

```bash
docker compose -f docker-compose.prod.yml down
```

**Start it again:**

```bash
docker compose -f docker-compose.prod.yml up -d
```

**Your data is preserved** in Docker volumes and will be available when you start again.

## Backing Up Your Data

### Database Backup

Create a backup of your database:

```bash
docker compose -f docker-compose.prod.yml exec db \
  mysqldump -u root -p openbiblio > backup-$(date +%Y%m%d).sql
```

Enter the `MYSQL_ROOT_PASSWORD` when prompted.

### Uploaded Pictures Backup

Your uploaded media pictures are stored in a Docker volume. To back them up:

```bash
docker run --rm -v openbiblio_pictures_data:/data -v $(pwd):/backup \
  alpine tar czf /backup/pictures-backup.tar.gz -C /data .
```

### Restore from Backup

**Restore database:**

```bash
docker compose -f docker-compose.prod.yml exec -T db \
  mysql -u root -p openbiblio < backup-20260507.sql
```

Enter the `MYSQL_ROOT_PASSWORD` when prompted.

## Updating OpenBiblio

1. **Back up your data** (see above)

2. **Update to the new version:**

   Edit `.env` and change the `VERSION` to the new release:

   ```env
   VERSION=1.1.0
   ```

3. **Pull the new image and restart:**

   ```bash
   docker compose -f docker-compose.prod.yml pull
   docker compose -f docker-compose.prod.yml down
   docker compose -f docker-compose.prod.yml up -d
   ```

4. **Run database upgrades** (if needed):

   Open `http://your-server/admin/upgrade.php` and enter your `OBIB_UPGRADE_KEY`.

## Production Deployment

For production use, we recommend:

1. **Use the production configuration:**
   ```bash
   docker compose -f docker-compose.prod.yml up -d
   ```

2. **Set up HTTPS with SSL/TLS certificates**

   OpenBiblio runs on HTTP by default. For production, you should add HTTPS encryption using one of these approaches:

   **Option A: Reverse Proxy (Recommended)**
   
   Place a reverse proxy (nginx, Traefik, or Caddy) in front of OpenBiblio to handle SSL/TLS termination. The proxy handles HTTPS and forwards requests to OpenBiblio via HTTP.
   
   - **Easiest:** Use [Caddy](https://caddyserver.com/) - it automatically obtains and renews SSL certificates from Let's Encrypt
   - **Most flexible:** Use nginx with Certbot for Let's Encrypt certificates
   - **For Docker environments:** Use [Traefik](https://traefik.io/) with automatic Let's Encrypt integration
   
   This approach keeps SSL configuration separate from OpenBiblio and is the most common production setup.

   **Option B: Cloud/Hosting Provider SSL**
   
   If you're using a cloud provider or hosting service, they often provide SSL termination:
   - AWS: Application Load Balancer with ACM certificates
   - Cloudflare: Automatic SSL/TLS
   - Most hosting providers: Built-in SSL certificate management
   
   **Option C: Manual SSL Certificates**
   
   If you have your own SSL certificates, configure your reverse proxy to use them. See [DOCKER-PRODUCTION.md](DOCKER-PRODUCTION.md) for advanced configurations.

   **Getting Free SSL Certificates:**
   
   [Let's Encrypt](https://letsencrypt.org/) provides free SSL certificates. Most reverse proxies can automatically obtain and renew them if you have a domain name pointing to your server.

3. **Use strong passwords** for all credentials

4. **Set up regular backups** (daily database dumps, weekly full backups)

5. **Keep your system updated** (pull new releases regularly)

For detailed production deployment options (external databases, custom images, advanced configurations), see [DOCKER-PRODUCTION.md](DOCKER-PRODUCTION.md).

## Troubleshooting

### Can't access OpenBiblio

- **Check if containers are running:**
  ```bash
  docker compose -f docker-compose.prod.yml ps
  ```
  All services should show "Up" or "healthy"

- **Check the logs:**
  ```bash
  docker compose -f docker-compose.prod.yml logs app
  docker compose -f docker-compose.prod.yml logs db
  ```

### Database connection errors

- Verify your `DB_PASSWORD` matches in the `.env` file
- Make sure the database container is running: `docker compose -f docker-compose.prod.yml ps db`
- Check database logs: `docker compose -f docker-compose.prod.yml logs db`

### Forgot admin password

Use the "Forgot Password" link on the login page. You'll need access to the email address associated with the admin account, or you can reset it directly in the database.

### Port already in use

If port 80 is already in use:

1. Edit `.env` and change `WEB_PORT`:
   ```env
   WEB_PORT=8080
   ```

2. Restart:
   ```bash
   docker compose -f docker-compose.prod.yml down
   docker compose -f docker-compose.prod.yml up -d
   ```

3. Access at `http://your-server-address:8080`

## Getting Help

- **Documentation:** See the `locale/en/help/` or `locale/de/help/` folders
- **Technical details:** See [DOCKER-PRODUCTION.md](DOCKER-PRODUCTION.md)
- **Issues:** Report bugs or ask questions on GitHub

## System Requirements

- **Minimum:** 1 CPU core, 512 MB RAM, 2 GB disk space
- **Recommended:** 2 CPU cores, 2 GB RAM, 10 GB disk space
- **Operating System:** Linux, macOS, or Windows with Docker Desktop
- **Docker:** Version 20.10 or newer
- **Docker Compose:** Version 2.0 or newer

## License

OpenBiblio is open source software distributed under the GNU General Public License.
See [GPL.txt](GPL.txt) for details.
