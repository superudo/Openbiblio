# OpenBiblio — Production Docker Deployment

This guide covers deploying OpenBiblio in production using Docker with immutable container images.

## Overview

The production deployment differs from development in several key ways:

- **Immutable images**: Application code is bundled into the container image during build, not mounted as volumes
- **Security**: No development files or git history in production images
- **External database support**: Can connect to databases outside the Docker stack
- **Restart policies**: Services automatically restart on failure
- **Required secrets**: All sensitive configuration must be explicitly set

## Quick Start

### 1. Build the production image

```bash
docker build --target production -t openbiblio:latest .
```

To build a specific version:

```bash
docker build --target production -t openbiblio:1.0.0 .
```

### 2. Configure environment

```bash
cp .env.example .env.prod
```

Edit `.env.prod` and set **all required variables** (see "Production Notes" section in the file):

- `DB_PASSWORD` — Database password
- `OBIB_UPGRADE_KEY` — Secret key for upgrade wizard (generate with `openssl rand -base64 32`)
- `OBIB_PWD_FORGOTTEN_KEY` — Secret key for password recovery (generate with `openssl rand -base64 32`)
- `MYSQL_ROOT_PASSWORD` — Only required if using bundled database

### 3. Deploy

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

The application will be available at `http://localhost` (or the port specified in `WEB_PORT`).

### 4. Initial setup

**Option A: Automated installation** (recommended for containerized/CI/CD deployments)

Set `INITIAL_ADMIN_PASSWORD` in `.env.prod` to enable fully automated installation. The database schema and admin account will be created automatically on first startup.

**Option B: Manual installation**

If `INITIAL_ADMIN_PASSWORD` is not set, open the install wizard:

```
http://your-server/install/index.php
```

Follow the wizard to create the database schema and initial admin account.

**Optional:** Set `INSTALL_TEST_DATA=yes` in `.env.prod` to load sample catalogue data (useful for demos, testing, or evaluation).

## Deployment Scenarios

### Scenario 1: Bundled Database (Default)

The default `docker-compose.prod.yml` includes a MariaDB service. This is suitable for:

- Single-server deployments
- Development/staging environments
- Small to medium installations

**Configuration:**

```env
DB_HOST=db
DB_NAME=openbiblio
DB_USER=openbiblio
DB_PASSWORD=your_secure_password
MYSQL_ROOT_PASSWORD=your_root_password
```

**Data persistence:** Database data is stored in the `db_data` Docker volume.

### Scenario 2: External Database

For production environments with existing database infrastructure:

1. **Configure database connection** in `.env.prod`:

   ```env
   DB_HOST=your-db-server.example.com
   DB_NAME=openbiblio
   DB_USER=openbiblio
   DB_PASSWORD=your_secure_password
   # MYSQL_ROOT_PASSWORD not needed
   ```

2. **Remove the database service** from `docker-compose.prod.yml`:

   Comment out or delete the entire `db` service block and the `db_data` volume.

3. **Ensure database exists**:

   Create the database on your external server:

   ```sql
   CREATE DATABASE openbiblio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'openbiblio'@'%' IDENTIFIED BY 'your_secure_password';
   GRANT ALL PRIVILEGES ON openbiblio.* TO 'openbiblio'@'%';
   FLUSH PRIVILEGES;
   ```

4. **Ensure network connectivity**:

   The Docker containers must be able to reach the database server. You may need to:
   - Configure firewall rules
   - Use Docker networks
   - Set up VPN or private networking

## Custom Media Type Icons

OpenBiblio allows you to add custom icons for new media types by placing image files in the `images/` folder. In production deployments, you can mount a local folder with custom images:

1. **Create a folder for custom images**:

   ```bash
   mkdir -p custom-images
   # Add your custom media type icons (e.g., boardgame.png, vinyl.gif)
   cp my-custom-icon.png custom-images/
   ```

2. **Enable the volume mount** in `docker-compose.prod.yml`:

   Uncomment these lines in both the `web` and `app` services:
   ```yaml
   # - ./custom-images:/var/www/html/images
   ```

3. **Restart the services**:

   ```bash
   docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
   ```

Your custom images will be available alongside the bundled icons. Files in the mounted folder take precedence over bundled files with the same name.

## Uploaded Media Pictures

OpenBiblio allows users to upload pictures for catalog items (books, games, etc.). These uploaded images are stored in the `pictures/` folder and need persistent storage.

### Default: Named Volume

By default, uploaded pictures are stored in a Docker named volume (`pictures_data`):

```yaml
- pictures_data:/var/www/html/pictures
```

This provides good performance and is managed by Docker.

### Alternative: Local Folder Mapping

For easier access to uploaded pictures or to integrate with backup systems, you can map to a local folder:

1. **Edit `docker-compose.prod.yml`**:

   In both `web` and `app` services, comment out the named volume and uncomment the local folder:
   ```yaml
   # - pictures_data:/var/www/html/pictures
   - ./data/pictures:/var/www/html/pictures
   ```

2. **Create the folder with proper permissions**:

   ```bash
   mkdir -p data/pictures
   # Web server needs write access (www-data is UID 33 in PHP-FPM container)
   sudo chown -R 33:33 data/pictures
   ```

3. **Restart services**:

   ```bash
   docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
   ```

**Backup uploaded pictures:**
```bash
# With named volume
docker run --rm -v openbiblio_pictures_data:/data -v $(pwd):/backup \
  alpine tar czf /backup/pictures-backup-$(date +%Y%m%d-%H%M%S).tar.gz -C /data .

# With local folder
tar czf pictures-backup-$(date +%Y%m%d-%H%M%S).tar.gz data/pictures
```

## Database Backup Strategies

### Option 1: Named Volume (Default)

The default configuration uses a Docker named volume (`db_data`). This is managed by Docker and provides good performance.

**Backup:**
```bash
# Create backup
docker compose -f docker-compose.prod.yml exec db \
  mysqldump -u root -p${MYSQL_ROOT_PASSWORD} openbiblio > backup-$(date +%Y%m%d-%H%M%S).sql

# Or backup the entire volume
docker run --rm -v openbiblio_db_data:/data -v $(pwd):/backup \
  alpine tar czf /backup/db-backup-$(date +%Y%m%d-%H%M%S).tar.gz -C /data .
```

**Restore:**
```bash
# From SQL dump
docker compose -f docker-compose.prod.yml exec -T db \
  mysql -u root -p${MYSQL_ROOT_PASSWORD} openbiblio < backup-20260507-143000.sql
```

### Option 2: Local Folder Mapping

For easier access to database files and simpler backup integration, you can map the database data to a local folder.

1. **Edit `docker-compose.prod.yml`**:

   In the `db` service, comment out the named volume and uncomment the local folder mapping:
   ```yaml
   volumes:
     # - db_data:/var/lib/mysql
     - ./data/mysql:/var/lib/mysql
   ```

2. **Create the data folder**:

   ```bash
   mkdir -p data/mysql
   ```

3. **Set proper permissions** (important for MariaDB):

   ```bash
   # MariaDB runs as mysql user (UID 999 in the container)
   sudo chown -R 999:999 data/mysql
   ```

4. **Restart the database service**:

   ```bash
   docker compose -f docker-compose.prod.yml --env-file .env.prod up -d db
   ```

**Backup with local folder:**
```bash
# Stop the database first to ensure consistency
docker compose -f docker-compose.prod.yml stop db

# Backup the folder
tar czf db-backup-$(date +%Y%m%d-%H%M%S).tar.gz data/mysql

# Restart the database
docker compose -f docker-compose.prod.yml start db
```

**Advantages:**
- Direct file access for backup tools
- Easy integration with existing backup systems
- Can use rsync, file-level snapshots, etc.

**Considerations:**
- Requires proper file permissions (UID 999)
- Performance may vary depending on host filesystem
- Not portable across different host systems

## Building and Versioning Images

### Manual build for specific version

```bash
# Build and tag
docker build --target production -t openbiblio:1.0.0 .
docker tag openbiblio:1.0.0 openbiblio:latest

# Update .env.prod
VERSION=1.0.0

# Deploy
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
```

### Multi-architecture builds

For deployment on different CPU architectures (e.g., ARM servers):

```bash
docker buildx build --platform linux/amd64,linux/arm64 \
  --target production \
  -t openbiblio:latest \
  .
```

## Upgrading

### Before upgrading

1. **Back up your database**:

   For bundled database:
   ```bash
   docker compose -f docker-compose.prod.yml exec db \
     mysqldump -u root -p openbiblio > backup-$(date +%Y%m%d).sql
   ```

   For external database, use your existing backup procedures.

2. **Back up your environment file**:
   ```bash
   cp .env.prod .env.prod.backup
   ```
   
   Note: The `.env.example` file contains defaults for both development and production - copy it to `.env.prod` and customize for your production deployment.

### Upgrade process

1. **Pull or build new image**:
   ```bash
   docker build --target production -t openbiblio:1.1.0 .
   ```

2. **Update version in `.env.prod`**:
   ```env
   VERSION=1.1.0
   ```

3. **Stop services**:
   ```bash
   docker compose -f docker-compose.prod.yml --env-file .env.prod down
   ```

4. **Start with new image**:
   ```bash
   docker compose -f docker-compose.prod.yml --env-file .env.prod up -d
   ```

5. **Run database migrations** (if required):

   Open the upgrade wizard:
   ```
   http://your-server/admin/upgrade.php
   ```

   Enter your `OBIB_UPGRADE_KEY` and follow the wizard.

## Security Considerations

### Required for production

- [ ] Change all default passwords
- [ ] Set strong random values for `OBIB_UPGRADE_KEY` and `OBIB_PWD_FORGOTTEN_KEY`
- [ ] Use HTTPS (place a reverse proxy like nginx or Traefik in front)
- [ ] Restrict database access (firewall rules, network policies)
- [ ] Keep `.env.prod` file permissions restricted (`chmod 600 .env.prod`)
- [ ] Regularly update base images and rebuild

### Recommended

- Use Docker secrets instead of environment variables for sensitive data
- Run behind a reverse proxy with rate limiting
- Enable database SSL/TLS connections
- Implement regular automated backups
- Monitor container logs for security events

## Monitoring and Logs

### View logs

```bash
# All services
docker compose -f docker-compose.prod.yml --env-file .env.prod logs -f

# Specific service
docker compose -f docker-compose.prod.yml --env-file .env.prod logs -f app
```

### Health checks

All services include health checks. Check status:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod ps
```

Healthy services show `healthy` in the status column.

## Troubleshooting

### Application won't start

1. Check logs: `docker compose -f docker-compose.prod.yml --env-file .env.prod logs app`
2. Verify all required environment variables are set
3. Ensure database is accessible and credentials are correct

### Database connection errors

1. Verify `DB_HOST` is correct
2. Check database service is running: `docker compose -f docker-compose.prod.yml --env-file .env.prod ps db`
3. Test connectivity: `docker compose -f docker-compose.prod.yml --env-file .env.prod exec app ping db`
4. For external database, verify firewall rules and network connectivity

### Permission errors

The application runs as `www-data` (UID 33). Ensure file permissions are correct:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod exec app ls -la /var/www/html
```

## Differences from Development Setup

| Aspect | Development | Production |
|--------|-------------|------------|
| **Code location** | Volume mount (live updates) | Bundled in image (immutable) |
| **Build target** | `development` | `production` |
| **Restart policy** | No automatic restart | `unless-stopped` |
| **Secrets** | Optional defaults | All required |
| **Database** | Always bundled | Optional (external supported) |
| **Port** | 8989 | 80 (configurable) |
| **Dev files** | Included | Removed from image |

## Future: Automated Image Publishing

Currently, images must be built manually. Future enhancements will include:

- Automated builds via GitHub Actions
- Publishing to Docker Hub or GitHub Container Registry
- Versioned image tags matching releases
- Multi-architecture builds

Until then, use the manual build process documented above.
